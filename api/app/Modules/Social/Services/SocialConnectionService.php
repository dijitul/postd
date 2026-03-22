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
     * Fetch and sync platform accounts (pages, profiles, etc.) for a connection.
     */
    public function syncAccounts(SocialConnection $connection): void
    {
        try {
            $platform = $this->getPlatform($connection->platform);
            $accounts = $platform->getAccounts($connection);

            foreach ($accounts as $account) {
                PlatformAccount::updateOrCreate(
                    [
                        'connection_id' => $connection->id,
                        'platform_account_id' => $account['id'],
                    ],
                    [
                        'account_name' => $account['name'],
                        'account_type' => $account['type'],
                        'account_url' => $account['url'] ?? null,
                        'avatar_url' => $account['metadata']['avatar_url'] ?? null,
                        'metadata' => $account['metadata'] ?? [],
                    ]
                );
            }

            // Auto-select the first account if none is selected yet
            if ($connection->platformAccounts()->where('is_selected', true)->doesntExist()) {
                $connection->platformAccounts()->first()?->update(['is_selected' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning("SocialConnectionService: Could not sync accounts for {$connection->platform}", [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }
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
