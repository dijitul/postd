<?php

namespace App\Modules\Auth\Controllers;

use App\Models\User;
use App\Modules\Social\Platforms\GoogleBusinessProfilePlatform;
use App\Modules\Social\Services\SocialConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController
{
    public function redirect(): \Illuminate\Http\JsonResponse
    {
        $url = Socialite::driver('google')
            ->scopes([
                'openid',
                'email',
                'profile',
                'https://www.googleapis.com/auth/business.manage',
            ])
            ->with([
                'access_type' => 'offline',
                'prompt'      => 'consent',
                'redirect_uri' => config('services.google.auth_redirect'),
            ])
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['redirect_url' => $url]);
    }

    public function callback(Request $request, SocialConnectionService $connectionService): \Illuminate\Http\RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', 'https://postd.uk');

        try {
            $socialUser = Socialite::driver('google')
                ->with(['redirect_uri' => config('services.google.auth_redirect')])
                ->stateless()
                ->user();
        } catch (\Throwable $e) {
            Log::error('GoogleAuth: Socialite error', ['error' => $e->getMessage()]);
            return redirect("{$frontendUrl}/login?error=oauth_failed");
        }

        // Find or create user
        $user  = User::where('email', $socialUser->getEmail())->first();
        $isNew = false;

        if (! $user) {
            $isNew = true;
            $user  = User::create([
                'name'              => $socialUser->getName() ?? $socialUser->getEmail(),
                'email'             => $socialUser->getEmail(),
                'email_verified_at' => now(),
                'password'          => bcrypt(Str::random(32)),
            ]);

            Log::info('GoogleAuth: New user created', ['user_id' => $user->id, 'email' => $user->email]);
        } else {
            Log::info('GoogleAuth: Existing user signed in', ['user_id' => $user->id]);
        }

        // Store Google tokens — if user has a business, create connection now;
        // otherwise cache them for 30 minutes so onboarding can pick them up.
        $tokenData = [
            'access_token'  => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_in'    => $socialUser->expiresIn,
            'platform_user_id' => $socialUser->getId(),
            'platform_username' => $socialUser->getEmail(),
            'scopes'        => $socialUser->approvedScopes ?? [],
            'raw'           => $socialUser->accessTokenResponseBody ?? [],
        ];

        $business = $user->business()->first();

        if ($business) {
            try {
                $connectionService->upsertConnection(
                    businessId:   $business->id,
                    platform:     'google_business_profile',
                    accessToken:  $tokenData['access_token'],
                    refreshToken: $tokenData['refresh_token'],
                    expiresAt:    $tokenData['expires_in'] ? now()->addSeconds($tokenData['expires_in']) : now()->addHour(),
                    scopes:       $tokenData['scopes'] ?? [],
                    rawTokenData: $tokenData['raw'] ?? [],
                );
                Log::info('GoogleAuth: GBP connection saved', ['business_id' => $business->id]);
            } catch (\Throwable $e) {
                Log::warning('GoogleAuth: Could not save GBP connection', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        } else {
            // Cache tokens for onboarding to pick up
            Cache::put("google_tokens_{$user->id}", $tokenData, now()->addMinutes(30));
            Log::info('GoogleAuth: Tokens cached for onboarding', ['user_id' => $user->id]);
        }

        // Cache GBP locations so onboarding can show a location picker
        try {
            $platform  = new GoogleBusinessProfilePlatform();
            $locations = $platform->getAccountsWithToken($tokenData['access_token']);
            if (! empty($locations)) {
                Cache::put("gbp_locations_{$user->id}", $locations, now()->addMinutes(30));
                Log::info('GoogleAuth: GBP locations cached', ['user_id' => $user->id, 'count' => count($locations)]);
            }
        } catch (\Throwable $e) {
            Log::warning('GoogleAuth: Could not cache GBP locations', ['error' => $e->getMessage()]);
        }

        $token      = $user->createToken('google-auth')->plainTextToken;
        $onboarded  = $user->business()->where('onboarding_complete', true)->exists();
        $redirectTo = $onboarded ? 'dashboard' : 'onboarding';

        return redirect("{$frontendUrl}/auth/callback?token={$token}&redirect={$redirectTo}");
    }
}
