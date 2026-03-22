<?php

namespace App\Modules\Social\Platforms;

use App\Models\SocialConnection;
use App\Modules\Social\Contracts\SocialPlatformInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class FacebookPlatform implements SocialPlatformInterface
{
    private readonly Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://graph.facebook.com/v19.0/',
            'timeout' => 30,
        ]);
    }

    public function publishPost(SocialConnection $connection, string $content, array $mediaUrls = []): array
    {
        $account = $connection->selectedAccount()->first();
        $pageId = $account?->platform_account_id ?? $this->getDefaultPageId($connection);

        if (! $pageId) {
            throw new \RuntimeException('No Facebook Page ID found. Please reconnect your Facebook account.');
        }

        // Get the page access token
        $pageToken = $this->getPageToken($connection->access_token, $pageId);

        $params = [
            'message' => $content,
            'access_token' => $pageToken,
        ];

        // Upload photos if provided
        if (! empty($mediaUrls)) {
            $photoId = $this->uploadPhoto($mediaUrls[0], $pageId, $pageToken);
            if ($photoId) {
                $params['attached_media'] = [['media_fbid' => $photoId]];
            }
        }

        $response = $this->client->post("{$pageId}/feed", ['form_params' => $params]);
        $data = json_decode((string) $response->getBody(), true);

        if (! isset($data['id'])) {
            throw new \RuntimeException('Facebook API returned no post ID: '.json_encode($data));
        }

        return [
            'platform_post_id' => $data['id'],
            'post_url' => "https://www.facebook.com/{$data['id']}",
            'status_code' => $response->getStatusCode(),
        ];
    }

    public function validateToken(SocialConnection $connection): bool
    {
        try {
            $appId = config('services.facebook.client_id');
            $appSecret = config('services.facebook.client_secret');
            $appToken = "{$appId}|{$appSecret}";

            $response = $this->client->get('debug_token', [
                'query' => [
                    'input_token' => $connection->access_token,
                    'access_token' => $appToken,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            return $data['data']['is_valid'] ?? false;
        } catch (\Throwable $e) {
            Log::warning("FacebookPlatform: Token validation failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function refreshToken(SocialConnection $connection): array
    {
        // Facebook long-lived tokens don't expire for 60 days
        // Exchange for a new long-lived token
        $response = $this->client->get('oauth/access_token', [
            'query' => [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.facebook.client_id'),
                'client_secret' => config('services.facebook.client_secret'),
                'fb_exchange_token' => $connection->access_token,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        if (! isset($data['access_token'])) {
            throw new \RuntimeException('Facebook token refresh failed: '.json_encode($data));
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => null,
            'expires_at' => isset($data['expires_in'])
                ? now()->addSeconds($data['expires_in'])
                : now()->addDays(60),
        ];
    }

    public function getAccounts(SocialConnection $connection): array
    {
        $response = $this->client->get('me/accounts', [
            'query' => [
                'access_token' => $connection->access_token,
                'fields' => 'id,name,category,fan_count,link',
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        return array_map(fn ($page) => [
            'id' => $page['id'],
            'name' => $page['name'],
            'type' => 'page',
            'url' => $page['link'] ?? null,
            'metadata' => [
                'category' => $page['category'] ?? null,
                'fan_count' => $page['fan_count'] ?? 0,
            ],
        ], $data['data'] ?? []);
    }

    private function getPageToken(string $userToken, string $pageId): string
    {
        $response = $this->client->get("{$pageId}", [
            'query' => [
                'fields' => 'access_token',
                'access_token' => $userToken,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);
        return $data['access_token'] ?? $userToken;
    }

    private function getDefaultPageId(SocialConnection $connection): ?string
    {
        try {
            $accounts = $this->getAccounts($connection);
            return $accounts[0]['id'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function uploadPhoto(string $imageUrl, string $pageId, string $pageToken): ?string
    {
        try {
            $response = $this->client->post("{$pageId}/photos", [
                'form_params' => [
                    'url' => $imageUrl,
                    'published' => false, // unpublished — will be attached to the post
                    'access_token' => $pageToken,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            return $data['id'] ?? null;
        } catch (\Throwable $e) {
            Log::warning("FacebookPlatform: Photo upload failed", ['error' => $e->getMessage()]);
            return null;
        }
    }
}
