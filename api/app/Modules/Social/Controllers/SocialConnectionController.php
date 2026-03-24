<?php

namespace App\Modules\Social\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SocialConnection;
use App\Modules\Social\Services\SocialConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SocialConnectionController extends Controller
{
    public function __construct(
        private readonly SocialConnectionService $connectionService
    ) {}

    /**
     * Get all social connections for the user's business.
     */
    public function index(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        if (! $business) {
            return response()->json(['connections' => []]);
        }

        $connections = $business->socialConnections()
            ->with('platformAccounts')
            ->get()
            ->map(fn ($conn) => [
                'id' => $conn->id,
                'platform' => $conn->platform,
                'is_active' => $conn->is_active,
                'is_expired' => $conn->isExpired(),
                'expires_at' => $conn->expires_at?->toIso8601String(),
                'last_used_at' => $conn->last_used_at?->toIso8601String(),
                'last_error_message' => $conn->last_error_message,
                'accounts' => $conn->platformAccounts->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->account_name,
                    'type' => $a->account_type,
                    'is_selected' => $a->is_selected,
                    'url' => $a->account_url,
                    'avatar_url' => $a->avatar_url,
                ]),
            ]);

        return response()->json(['connections' => $connections]);
    }

    /**
     * Initiate an OAuth redirect for a social platform.
     */
    public function redirect(Request $request, string $platform): JsonResponse
    {
        $this->validatePlatform($platform);

        // Store a state token in cache to verify the callback
        $state = Str::random(40);
        Cache::put("oauth_state_{$state}", [
            'user_id' => $request->user()->id,
            'platform' => $platform,
        ], now()->addMinutes(10));

        $extraParams = ['state' => $state];
        $driver = Socialite::driver($this->getSocialiteDriver($platform))->stateless();

        // Google requires offline access_type to issue a refresh token,
        // prompt=consent to guarantee one is returned every time, and
        // the business.manage scope to access the Business Profile API.
        if ($platform === 'google_business_profile') {
            $extraParams['access_type'] = 'offline';
            $extraParams['prompt']      = 'consent';
            $driver = $driver->scopes(['https://www.googleapis.com/auth/business.manage']);
        }

        // Twitter OAuth 2.0 requires explicit scopes.
        // offline.access is needed to receive a refresh token.
        if ($platform === 'twitter') {
            $driver = $driver->scopes(['tweet.read', 'tweet.write', 'users.read', 'offline.access']);
        }

        $redirectUrl = $driver
            ->with($extraParams)
            ->redirect()
            ->getTargetUrl();

        return response()->json(['redirect_url' => $redirectUrl]);
    }

    /**
     * Handle the OAuth callback and save the connection, then redirect back to the frontend.
     */
    public function callback(Request $request, string $platform): \Illuminate\Http\RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'https://postd.uk'), '/');
        $redirectBase = $frontendUrl . '/platforms';

        $this->validatePlatform($platform);

        // Verify state to prevent CSRF
        $state = $request->query('state');
        $cached = Cache::pull("oauth_state_{$state}");

        if (! $cached || $cached['platform'] !== $platform) {
            Log::warning('OAuth callback: invalid or expired state', [
                'platform' => $platform,
                'state'    => $state,
                'cached'   => $cached,
            ]);
            return redirect($redirectBase . '?error=invalid_state');
        }

        $user = \App\Models\User::find($cached['user_id']);
        if (! $user) {
            Log::error('OAuth callback: user not found', ['user_id' => $cached['user_id'], 'platform' => $platform]);
            return redirect($redirectBase . '?error=user_not_found');
        }

        $business = $user->business;
        if (! $business) {
            Log::error('OAuth callback: no business for user', ['user_id' => $user->id, 'platform' => $platform]);
            return redirect($redirectBase . '?error=no_business');
        }

        try {
            $socialUser = Socialite::driver($this->getSocialiteDriver($platform))
                ->stateless()
                ->user();
        } catch (\Throwable $e) {
            Log::error('OAuth callback: Socialite exception', [
                'platform' => $platform,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
            return redirect($redirectBase . '?error=oauth_failed');
        }

        try {
            $this->connectionService->upsertConnection(
                businessId: $business->id,
                platform: $platform,
                accessToken: $socialUser->token,
                refreshToken: $socialUser->refreshToken,
                expiresAt: isset($socialUser->expiresIn) ? now()->addSeconds($socialUser->expiresIn) : null,
                scopes: $socialUser->approvedScopes ?? [],
                rawTokenData: ['id' => $socialUser->getId(), 'name' => $socialUser->getName()],
            );
        } catch (\Throwable $e) {
            Log::error('OAuth callback: upsertConnection failed', [
                'platform' => $platform,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
            return redirect($redirectBase . '?error=save_failed');
        }

        return redirect($redirectBase . '?connected=' . $platform);
    }

    /**
     * Initiate connection (alias for redirect, used when not in OAuth flow).
     */
    public function connect(Request $request, string $platform): JsonResponse
    {
        return $this->redirect($request, $platform);
    }

    /**
     * Disconnect a social platform.
     */
    public function disconnect(Request $request, string $id): JsonResponse
    {
        $connection = SocialConnection::findOrFail($id);

        $this->authoriseConnection($request, $connection);

        $connection->delete();

        return response()->json([
            'message' => ucfirst($connection->platform).' disconnected successfully.',
        ]);
    }

    /**
     * Manually trigger a token refresh.
     */
    public function refreshToken(Request $request, string $id): JsonResponse
    {
        $connection = SocialConnection::findOrFail($id);
        $this->authoriseConnection($request, $connection);

        try {
            $connection = $this->connectionService->refreshToken($connection);
            return response()->json([
                'message' => 'Token refreshed successfully.',
                'expires_at' => $connection->expires_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Token refresh failed: '.$e->getMessage(),
                'error' => 'refresh_failed',
            ], 422);
        }
    }

    /**
     * Get available accounts/pages for a connection.
     */
    public function accounts(Request $request, string $id): JsonResponse
    {
        $connection = SocialConnection::findOrFail($id);
        $this->authoriseConnection($request, $connection);

        $this->connectionService->syncAccounts($connection);
        $connection->refresh();

        return response()->json([
            'accounts' => $connection->platformAccounts->map(fn ($a) => [
                'id' => $a->id,
                'platform_account_id' => $a->platform_account_id,
                'name' => $a->account_name,
                'type' => $a->account_type,
                'is_selected' => $a->is_selected,
                'url' => $a->account_url,
                'avatar_url' => $a->avatar_url,
                'metadata' => $a->metadata,
            ]),
        ]);
    }

    /**
     * Select which account/page to post to for a connection.
     */
    public function selectAccount(Request $request, string $id): JsonResponse
    {
        $request->validate(['account_id' => ['required', 'uuid']]);

        $connection = SocialConnection::findOrFail($id);
        $this->authoriseConnection($request, $connection);

        // Deselect all, then select the chosen one
        $connection->platformAccounts()->update(['is_selected' => false]);
        $connection->platformAccounts()
            ->where('id', $request->account_id)
            ->update(['is_selected' => true]);

        return response()->json(['message' => 'Account selected.']);
    }

    private function authoriseConnection(Request $request, SocialConnection $connection): void
    {
        $business = $request->user()->business;
        if (! $business || $connection->business_id !== $business->id) {
            abort(403, 'You do not have permission to manage this connection.');
        }
    }

    private function validatePlatform(string $platform): void
    {
        $valid = ['facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 'google_business_profile'];
        if (! in_array($platform, $valid)) {
            abort(400, "Unknown platform: {$platform}");
        }
    }

    private function getSocialiteDriver(string $platform): string
    {
        return match ($platform) {
            'twitter' => 'twitter-oauth-2',   // OAuth 2.0 PKCE — matches TwitterPlatform's Bearer token usage
            'google_business_profile' => 'google',
            default => $platform,
        };
    }

    private function getPlatformDisplayName(string $platform): string
    {
        return match ($platform) {
            'google_business_profile' => 'Google Business Profile',
            'twitter' => 'X (Twitter)',
            default => ucfirst($platform),
        };
    }
}
