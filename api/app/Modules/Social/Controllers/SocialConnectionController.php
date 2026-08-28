<?php

namespace App\Modules\Social\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SocialConnection;
use App\Modules\Social\Platforms\LinkedInPlatform;
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
                // Whether the user must actually reconnect. An expired access
                // token we hold a refresh token for is not a broken connection.
                'needs_reconnect' => $conn->needsReconnect(),
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

        $state = Str::random(40);

        // Twitter OAuth 2.0 PKCE must be handled manually — Socialite's twitter-oauth-2
        // driver stores the code verifier in the session, but this is a stateless API
        // with no session middleware. We generate PKCE ourselves and stash the verifier
        // in our existing Redis state cache so the callback can retrieve it.
        if ($platform === 'twitter') {
            $codeVerifier  = Str::random(96);
            $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

            Cache::put("oauth_state_{$state}", [
                'user_id'       => $request->user()->id,
                'platform'      => 'twitter',
                'code_verifier' => $codeVerifier,
            ], now()->addMinutes(10));

            $redirectUrl = 'https://twitter.com/i/oauth2/authorize?' . http_build_query([
                'response_type'         => 'code',
                'client_id'             => config('services.twitter-oauth-2.client_id'),
                'redirect_uri'          => config('services.twitter-oauth-2.redirect'),
                'scope'                 => 'tweet.read tweet.write users.read offline.access',
                'state'                 => $state,
                'code_challenge'        => $codeChallenge,
                'code_challenge_method' => 'S256',
            ]);

            return response()->json(['redirect_url' => $redirectUrl]);
        }

        // Store state for other platforms
        Cache::put("oauth_state_{$state}", [
            'user_id' => $request->user()->id,
            'platform' => $platform,
        ], now()->addMinutes(10));

        // LinkedIn is built by hand rather than through Socialite. The Community
        // Management API must be the only product on the developer app, so we hold
        // no OpenID Connect or profile scope — and every Socialite LinkedIn driver
        // fetches a profile endpoint to build its user object, so both blow up on
        // callback. We only ever post as an organisation, so there is no profile to
        // fetch in the first place.
        if ($platform === 'linkedin') {
            $redirectUrl = 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
                'response_type' => 'code',
                'client_id'     => config('services.linkedin.client_id'),
                'redirect_uri'  => config('services.linkedin.redirect'),
                'state'         => $state,
                'scope'         => implode(' ', LinkedInPlatform::SCOPES),
            ]);

            return response()->json(['redirect_url' => $redirectUrl]);
        }

        $extraParams = ['state' => $state];
        $driver = Socialite::driver($this->getSocialiteDriver($platform))->stateless();

        // Google requires offline access_type to issue a refresh token and
        // prompt=consent to guarantee one is returned every time.
        if ($platform === 'google_business_profile') {
            $extraParams['access_type'] = 'offline';
            $extraParams['prompt']      = 'consent';
            $driver = $driver->scopes(['https://www.googleapis.com/auth/business.manage']);
        }

        // Facebook needs explicit Page permissions. Socialite's default scope is
        // email only, which yields a token that cannot list Pages (me/accounts
        // comes back empty) let alone post to one.
        //
        // setScopes() rather than scopes(): the latter MERGES with Socialite's
        // default, leaving "email" in the request. This app cannot request email,
        // and Meta rejects the entire dialog with "Invalid Scopes: email" rather
        // than ignoring the one bad scope. We only need Page access regardless.
        if ($platform === 'facebook') {
            $driver = $driver->setScopes([
                'pages_show_list',        // enumerate the Pages the user manages
                'pages_read_engagement',  // required alongside manage_posts by Graph
                'pages_manage_posts',     // create posts on a Page
                'business_management',    // Pages owned via a Business Manager
            ]);
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

        // Twitter: exchange code manually using the PKCE verifier we stored at redirect time.
        // Socialite's twitter-oauth-2 driver requires a session for PKCE which isn't
        // available in a stateless API context.
        if ($platform === 'twitter') {
            $code         = $request->query('code');
            $codeVerifier = $cached['code_verifier'] ?? null;

            if (! $code || ! $codeVerifier) {
                Log::error('OAuth callback: Twitter missing code or PKCE verifier', ['user_id' => $user->id]);
                return redirect($redirectBase . '?error=oauth_failed');
            }

            try {
                $http = new \GuzzleHttp\Client();

                // Exchange auth code for access + refresh token
                $tokenResp = $http->post('https://api.twitter.com/2/oauth2/token', [
                    'auth'        => [config('services.twitter-oauth-2.client_id'), config('services.twitter-oauth-2.client_secret')],
                    'form_params' => [
                        'code'          => $code,
                        'grant_type'    => 'authorization_code',
                        'redirect_uri'  => config('services.twitter-oauth-2.redirect'),
                        'code_verifier' => $codeVerifier,
                    ],
                ]);
                $tokenData = json_decode((string) $tokenResp->getBody(), true);

                if (empty($tokenData['access_token'])) {
                    throw new \RuntimeException('No access token: ' . json_encode($tokenData));
                }

                // Fetch the authenticated user's profile
                $userResp = $http->get('https://api.twitter.com/2/users/me', [
                    'headers' => ['Authorization' => 'Bearer ' . $tokenData['access_token']],
                    'query'   => ['user.fields' => 'name,username'],
                ]);
                $userData = json_decode((string) $userResp->getBody(), true);

                $this->connectionService->upsertConnection(
                    businessId:   $business->id,
                    platform:     'twitter',
                    accessToken:  $tokenData['access_token'],
                    refreshToken: $tokenData['refresh_token'] ?? null,
                    expiresAt:    isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in']) : null,
                    scopes:       explode(' ', $tokenData['scope'] ?? ''),
                    rawTokenData: [
                        'id'       => $userData['data']['id'] ?? null,
                        'username' => $userData['data']['username'] ?? null,
                        'name'     => $userData['data']['name'] ?? null,
                    ],
                );

                Log::info('OAuth callback: Twitter connected', ['business_id' => $business->id]);
            } catch (\Throwable $e) {
                Log::error('OAuth callback: Twitter failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return redirect($redirectBase . '?error=oauth_failed');
            }

            return redirect($redirectBase . '?connected=twitter');
        }

        // LinkedIn: exchange the code ourselves. See redirect() — no Socialite
        // driver survives a Community-Management-only app, and there is no profile
        // to fetch afterwards, so we go straight from token to Company Pages.
        if ($platform === 'linkedin') {
            $code = $request->query('code');

            if (! $code) {
                Log::error('OAuth callback: LinkedIn returned no code', [
                    'user_id' => $user->id,
                    'error'   => $request->query('error_description') ?? $request->query('error'),
                ]);
                return redirect($redirectBase . '?error=oauth_failed');
            }

            try {
                $http = new \GuzzleHttp\Client();

                $tokenResp = $http->post('https://www.linkedin.com/oauth/v2/accessToken', [
                    'form_params' => [
                        'grant_type'    => 'authorization_code',
                        'code'          => $code,
                        'redirect_uri'  => config('services.linkedin.redirect'),
                        'client_id'     => config('services.linkedin.client_id'),
                        'client_secret' => config('services.linkedin.client_secret'),
                    ],
                ]);
                $tokenData = json_decode((string) $tokenResp->getBody(), true);

                if (empty($tokenData['access_token'])) {
                    throw new \RuntimeException('No access token: ' . json_encode($tokenData));
                }

                // refresh_token is only ever present for approved LinkedIn partners.
                // Everyone else gets a 60 day access token and must reconnect.
                $connection = $this->connectionService->upsertConnection(
                    businessId:   $business->id,
                    platform:     'linkedin',
                    accessToken:  $tokenData['access_token'],
                    refreshToken: $tokenData['refresh_token'] ?? null,
                    expiresAt:    isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in']) : null,
                    scopes:       explode(' ', $tokenData['scope'] ?? ''),
                    rawTokenData: [],
                );

                // A LinkedIn connection is useless without a Page to post to. Say so
                // now rather than letting the first scheduled post fail days later.
                if ($connection->platformAccounts()->doesntExist()) {
                    Log::warning('OAuth callback: LinkedIn connected but user administers no Company Page', [
                        'business_id' => $business->id,
                    ]);
                    return redirect($redirectBase . '?error=linkedin_no_pages');
                }

                Log::info('OAuth callback: LinkedIn connected', ['business_id' => $business->id]);
            } catch (\Throwable $e) {
                Log::error('OAuth callback: LinkedIn failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return redirect($redirectBase . '?error=oauth_failed');
            }

            return redirect($redirectBase . '?connected=linkedin');
        }

        // All other platforms via Socialite
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
                businessId:   $business->id,
                platform:     $platform,
                accessToken:  $socialUser->token,
                refreshToken: $socialUser->refreshToken,
                expiresAt:    isset($socialUser->expiresIn) ? now()->addSeconds($socialUser->expiresIn) : null,
                scopes:       $socialUser->approvedScopes ?? [],
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
