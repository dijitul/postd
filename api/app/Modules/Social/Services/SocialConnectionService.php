<?php

namespace App\Modules\Social\Services;

use App\Models\PlatformAccount;
use App\Models\SocialConnection;
use App\Modules\Social\Contracts\SocialPlatformInterface;
use App\Modules\Social\Platforms\FacebookPlatform;
use App\Modules\Social\Platforms\GoogleBusinessProfilePlatform;
use App\Modules\Social\Platforms\InstagramPlatform;
use App\Modules\Social\Platforms\LinkedInPlatform;
use App\Modules\Social\Platforms\TikTokPlatform;
use App\Modules\Social\Platforms\TwitterPlatform;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialConnectionService
{
    private array $platformMap;

    public function __construct(
        FacebookPlatform $facebook,
        InstagramPlatform $instagram,
        TwitterPlatform $twitter,
        LinkedInPlatform $linkedin,
        TikTokPlatform $tiktok,
        GoogleBusinessProfilePlatform $gbp,
    ) {
        $this->platformMap = [
            'facebook' => $facebook,
            'instagram' => $instagram,
            'twitter' => $twitter,
            'linkedin' => $linkedin,
            'tiktok' => $tiktok,
            'google_business_profile' => $gbp,
        ];
    }

    /**
     * Get the platform handler for a given platform name.
     */
    public function getPlatform(string $platform): SocialPlatformInterface
    {
        $handler = $this->platformMap[$platform] ?? null;
        if (! $handler) {
            throw new \InvalidArgumentException("Unknown platform: {$platform}");
        }
        return $handler;
    }

    /**
     * Create or update a SocialConnection from an OAuth callback.
     */
    public function upsertConnection(
        string $businessId,
        string $platform,
        string $accessToken,
        ?string $refreshToken,
        ?\DateTimeInterface $expiresAt,
        array $scopes = [],
        array $rawTokenData = []
    ): SocialConnection {
        // Facebook hands back a short-lived user token (~1-2 hours) that cannot be
        // refreshed once it lapses — there is no refresh token, only an exchange
        // that requires a still-valid token. Trade it for the 60 day long-lived one
        // immediately, or the connection quietly dies within the hour.
        if ($platform === 'facebook') {
            try {
                $exchanged = $this->exchangeFacebookToken($accessToken);
                $accessToken = $exchanged['access_token'];
                $expiresAt = $exchanged['expires_at'];
            } catch (\Throwable $e) {
                Log::warning('SocialConnectionService: Facebook long-lived token exchange failed, keeping short-lived token', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $connection = SocialConnection::withTrashed()
            ->where('business_id', $businessId)
            ->where('platform', $platform)
            ->first();

        if ($connection) {
            $connection->restore();
            $connection->update([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => $expiresAt,
                'is_active' => true,
                'scopes' => $scopes,
                'raw_token_data' => $rawTokenData,
                'last_error_at' => null,
                'last_error_message' => null,
            ]);
        } else {
            $connection = SocialConnection::create([
                'business_id' => $businessId,
                'platform' => $platform,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => $expiresAt,
                'is_active' => true,
                'scopes' => $scopes,
                'raw_token_data' => $rawTokenData,
            ]);
        }

        // Sync the accounts for this connection
        $this->syncAccounts($connection);

        return $connection;
    }

    /**
     * Swap a short-lived Facebook user token for a long-lived (60 day) one.
     *
     * @return array{access_token: string, expires_at: \Illuminate\Support\Carbon}
     */
    private function exchangeFacebookToken(string $shortLivedToken): array
    {
        $response = Http::timeout(20)->get('https://graph.facebook.com/v19.0/oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => config('services.facebook.client_id'),
            'client_secret'     => config('services.facebook.client_secret'),
            'fb_exchange_token' => $shortLivedToken,
        ]);

        $data = $response->json() ?? [];

        if (! $response->successful() || empty($data['access_token'])) {
            throw new \RuntimeException(
                'Facebook token exchange failed: '.($data['error']['message'] ?? $response->body())
            );
        }

        return [
            'access_token' => $data['access_token'],
            'expires_at'   => isset($data['expires_in'])
                ? now()->addSeconds((int) $data['expires_in'])
                : now()->addDays(60),
        ];
    }

    /**
     * Fetch and sync platform accounts (pages, profiles, etc.) for a connection.
     */
    public function syncAccounts(SocialConnection $connection): void
    {
        try {
            $platform = $this->getPlatform($connection->platform);
            $accounts = $platform->getAccounts($connection);

            foreach ($accounts as $account) {
                $model = PlatformAccount::firstOrNew([
                    'connection_id' => $connection->id,
                    'platform_account_id' => $account['id'],
                ]);

                $model->fill([
                    'account_name' => $account['name'],
                    'account_type' => $account['type'],
                    'account_url' => $account['url'] ?? null,
                    'avatar_url' => $account['metadata']['avatar_url'] ?? null,
                    'metadata' => $account['metadata'] ?? [],
                ]);

                // Never select on create. The is_selected column defaults to true,
                // so letting the default stand marked every account as the posting
                // target at once and left the real destination to whatever order
                // the database happened to return. Set it explicitly and let the
                // reconciliation below decide. A re-sync must not clobber a choice
                // the user has already made, so existing rows keep their value.
                if (! $model->exists) {
                    $model->is_selected = false;
                }

                $model->save();
            }

            $this->reconcileSelectedAccount($connection);
        } catch (\Throwable $e) {
            Log::warning("SocialConnectionService: Could not sync accounts for {$connection->platform}", [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Guarantee exactly one account on a connection is the posting target.
     *
     * Every platform resolves its destination with selectedAccount()->first(), so
     * more than one selected row means the Page, Profile or Location a post lands
     * on is decided by database ordering. Fewer than one means nothing can post
     * at all. This collapses both cases, and also repairs connections synced
     * before the create path stopped relying on the column default.
     */
    private function reconcileSelectedAccount(SocialConnection $connection): void
    {
        $selected = $connection->platformAccounts()->where('is_selected', true)->get();

        if ($selected->count() === 1) {
            return;
        }

        // Keep the earliest selected row rather than picking arbitrarily, so a
        // repair does not move an established connection to a different target.
        $keep = $selected->first() ?? $connection->platformAccounts()->first();

        if (! $keep) {
            return; // nothing to select — the user administers no accounts
        }

        $connection->platformAccounts()
            ->whereKeyNot($keep->getKey())
            ->update(['is_selected' => false]);

        $keep->update(['is_selected' => true]);
    }

    /**
     * Refresh an access token for a connection.
     */
    public function refreshToken(SocialConnection $connection): SocialConnection
    {
        $platform = $this->getPlatform($connection->platform);

        $tokenData = $platform->refreshToken($connection);

        $connection->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $connection->refresh_token,
            'expires_at' => $tokenData['expires_at'] ?? null,
            'token_refresh_attempted' => true,
            'is_active' => true,
            'last_error_at' => null,
            'last_error_message' => null,
        ]);

        Log::info("SocialConnectionService: Token refreshed for {$connection->platform}", [
            'connection_id' => $connection->id,
        ]);

        return $connection->fresh();
    }

    /**
     * Validate all active connections and flag expired or invalid ones.
     */
    public function validateAllTokens(): array
    {
        $results = [];

        $connections = SocialConnection::with('business')
            ->where('is_active', true)
            ->get();

        foreach ($connections as $connection) {
            try {
                $platform = $this->getPlatform($connection->platform);
                $isValid = $platform->validateToken($connection);

                $results[] = [
                    'connection_id' => $connection->id,
                    'platform' => $connection->platform,
                    'business_id' => $connection->business_id,
                    'valid' => $isValid,
                ];

                if (! $isValid) {
                    // Attempt refresh if we have a refresh token
                    if ($connection->refresh_token) {
                        try {
                            $this->refreshToken($connection);
                        } catch (\Throwable $refreshError) {
                            $connection->update([
                                'is_active' => false,
                                'last_error_at' => now(),
                                'last_error_message' => 'Token validation failed and refresh failed: '.$refreshError->getMessage(),
                            ]);
                        }
                    } else {
                        $connection->update([
                            'is_active' => false,
                            'last_error_at' => now(),
                            'last_error_message' => 'Token validation failed.',
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("SocialConnectionService: Validation error for {$connection->platform}", [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
