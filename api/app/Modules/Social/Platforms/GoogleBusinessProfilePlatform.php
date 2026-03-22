<?php

namespace App\Modules\Social\Platforms;

use App\Models\SocialConnection;
use App\Modules\Social\Contracts\SocialPlatformInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class GoogleBusinessProfilePlatform implements SocialPlatformInterface
{
    private readonly Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://mybusiness.googleapis.com/v4/',
            'timeout' => 30,
        ]);
    }

    public function publishPost(SocialConnection $connection, string $content, array $mediaUrls = []): array
    {
        $account = $connection->selectedAccount()->first();
        $locationName = $account?->platform_account_id;

        if (! $locationName) {
            $locationName = $this->getFirstLocation($connection);
        }

        if (! $locationName) {
            throw new \RuntimeException('No Google Business Profile location found. Please reconnect your account.');
        }

        $localPost = [
            'languageCode' => 'en-GB',
            'summary' => $content,
            'callToAction' => [
                'actionType' => 'LEARN_MORE',
                'url' => $connection->business->website_url ?? '',
            ],
            'topicType' => 'STANDARD',
        ];

        if (! empty($mediaUrls)) {
            $localPost['media'] = [[
                'mediaFormat' => 'PHOTO',
                'sourceUrl' => $mediaUrls[0],
            ]];
        }

        $response = $this->client->post("{$locationName}/localPosts", [
            'headers' => $this->buildHeaders($connection),
            'json' => $localPost,
        ]);

        $data = json_decode((string) $response->getBody(), true);
        $postName = $data['name'] ?? null;

        if (! $postName) {
            throw new \RuntimeException('Google Business Profile API returned no post name: '.json_encode($data));
        }

        return [
            'platform_post_id' => $postName,
            'post_url' => $data['searchUrl'] ?? null,
            'status_code' => $response->getStatusCode(),
        ];
    }

    public function validateToken(SocialConnection $connection): bool
    {
        try {
            $response = $this->client->get('accounts', [
                'headers' => $this->buildHeaders($connection),
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    public function refreshToken(SocialConnection $connection): array
    {
        // Google OAuth 2.0 token refresh
        $response = (new Client())->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        if (! isset($data['access_token'])) {
            throw new \RuntimeException('Google token refresh failed: '.json_encode($data));
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $connection->refresh_token, // Google refresh tokens don't rotate
            'expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : now()->addHour(),
        ];
    }

    public function getAccounts(SocialConnection $connection): array
    {
        try {
            // Get all GBP accounts
            $accountsResponse = $this->client->get('accounts', [
                'headers' => $this->buildHeaders($connection),
            ]);
            $accounts = json_decode((string) $accountsResponse->getBody(), true);

            $locations = [];
            foreach ($accounts['accounts'] ?? [] as $account) {
                // Get locations for each account
                try {
                    $locationsResponse = $this->client->get("{$account['name']}/locations", [
                        'headers' => $this->buildHeaders($connection),
                        'query' => ['readMask' => 'name,title,websiteUri,phoneNumbers,profile'],
                    ]);

                    $locData = json_decode((string) $locationsResponse->getBody(), true);
                    foreach ($locData['locations'] ?? [] as $location) {
                        $locations[] = [
                            'id' => $location['name'],
                            'name' => $location['title'] ?? 'Business Location',
                            'type' => 'location',
                            'url' => $location['websiteUri'] ?? null,
                            'metadata' => [
                                'phone' => $location['phoneNumbers']['primaryPhone'] ?? null,
                                'profile_description' => $location['profile']['description'] ?? null,
                            ],
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning("GoogleBusinessProfilePlatform: Could not get locations for account {$account['name']}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $locations;
        } catch (\Throwable $e) {
            Log::error('GoogleBusinessProfilePlatform: getAccounts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function buildHeaders(SocialConnection $connection): array
    {
        return [
            'Authorization' => "Bearer {$connection->access_token}",
            'Content-Type' => 'application/json',
        ];
    }

    private function getFirstLocation(SocialConnection $connection): ?string
    {
        $accounts = $this->getAccounts($connection);
        return $accounts[0]['id'] ?? null;
    }
}
