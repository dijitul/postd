<?php

namespace App\Modules\Social\Platforms;

use App\Models\SocialConnection;
use App\Modules\Social\Contracts\SocialPlatformInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class TwitterPlatform implements SocialPlatformInterface
{
    private readonly Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.twitter.com/2/',
            'timeout' => 30,
        ]);
    }

    public function publishPost(SocialConnection $connection, string $content, array $mediaUrls = []): array
    {
        $payload = ['text' => $content];

        // Upload media if provided
        if (! empty($mediaUrls)) {
            $mediaIds = [];
            foreach (array_slice($mediaUrls, 0, 4) as $url) { // Twitter allows up to 4 images
                $mediaId = $this->uploadMedia($url, $connection->access_token);
                if ($mediaId) {
                    $mediaIds[] = $mediaId;
                }
            }
            if (! empty($mediaIds)) {
                $payload['media'] = ['media_ids' => $mediaIds];
            }
        }

        $response = $this->client->post('tweets', [
            'headers' => $this->buildAuthHeaders($connection),
            'json' => $payload,
        ]);

        $data = json_decode((string) $response->getBody(), true);

        if (! isset($data['data']['id'])) {
            throw new \RuntimeException('Twitter API returned no post ID: '.json_encode($data));
        }

        $tweetId = $data['data']['id'];

        return [
            'platform_post_id' => $tweetId,
            'post_url' => "https://x.com/i/web/status/{$tweetId}",
            'status_code' => $response->getStatusCode(),
        ];
    }

    public function validateToken(SocialConnection $connection): bool
    {
        try {
            $response = $this->client->get('users/me', [
                'headers' => $this->buildAuthHeaders($connection),
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    public function refreshToken(SocialConnection $connection): array
    {
        // Twitter OAuth 2.0 PKCE token refresh
        $response = $this->client->post('oauth2/token', [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
            ],
            'auth' => [
                config('services.twitter-oauth-2.client_id'),
                config('services.twitter-oauth-2.client_secret'),
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        if (! isset($data['access_token'])) {
            throw new \RuntimeException('Twitter token refresh failed: '.json_encode($data));
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
            'expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
        ];
    }

    public function getAccounts(SocialConnection $connection): array
    {
        try {
            $response = $this->client->get('users/me', [
                'headers' => $this->buildAuthHeaders($connection),
                'query' => ['user.fields' => 'name,username,profile_image_url,public_metrics'],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            $user = $data['data'] ?? [];

            return [[
                'id' => $user['id'] ?? '',
                'name' => $user['name'] ?? '',
                'type' => 'profile',
                'url' => isset($user['username']) ? "https://x.com/{$user['username']}" : null,
                'metadata' => [
                    'username' => $user['username'] ?? null,
                    'followers_count' => $user['public_metrics']['followers_count'] ?? 0,
                    'avatar_url' => $user['profile_image_url'] ?? null,
                ],
            ]];
        } catch (\Throwable $e) {
            Log::error('TwitterPlatform: getAccounts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function buildAuthHeaders(SocialConnection $connection): array
    {
        return [
            'Authorization' => "Bearer {$connection->access_token}",
            'Content-Type' => 'application/json',
        ];
    }

    private function uploadMedia(string $imageUrl, string $token): ?string
    {
        try {
            // Download the image
            $imageData = file_get_contents($imageUrl);
            if ($imageData === false) {
                return null;
            }

            // Twitter v1.1 media upload (still required for v2)
            $uploadClient = new Client(['base_uri' => 'https://upload.twitter.com/1.1/']);

            $response = $uploadClient->post('media/upload.json', [
                'headers' => ['Authorization' => "Bearer {$token}"],
                'multipart' => [[
                    'name' => 'media_data',
                    'contents' => base64_encode($imageData),
                ]],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            return $data['media_id_string'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('TwitterPlatform: Media upload failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
