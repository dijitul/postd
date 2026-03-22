<?php

namespace App\Modules\Social\Platforms;

use App\Models\SocialConnection;
use App\Modules\Social\Contracts\SocialPlatformInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class InstagramPlatform implements SocialPlatformInterface
{
    private readonly Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://graph.facebook.com/v19.0/',
            'timeout' => 30,
        ]);
    }

    /**
     * Publish a post to Instagram Business Account via Graph API.
     * Two-step process: create container → publish container.
     */
    public function publishPost(SocialConnection $connection, string $content, array $mediaUrls = []): array
    {
        $account = $connection->selectedAccount()->first();
        $igUserId = $account?->platform_account_id;

        if (! $igUserId) {
            throw new \RuntimeException('No Instagram Business Account ID found. Please reconnect.');
        }

        $token = $connection->access_token;

        // Step 1: Create media container
        $containerId = $this->createContainer($igUserId, $content, $mediaUrls, $token);

        // Step 2: Publish the container
        $response = $this->client->post("{$igUserId}/media_publish", [
            'form_params' => [
                'creation_id' => $containerId,
                'access_token' => $token,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        if (! isset($data['id'])) {
            throw new \RuntimeException('Instagram publish failed: '.json_encode($data));
        }

        $postId = $data['id'];

        // Get the permalink
        $permalink = $this->getPermalink($postId, $token);

        return [
            'platform_post_id' => $postId,
            'post_url' => $permalink,
            'status_code' => $response->getStatusCode(),
        ];
    }

    private function createContainer(string $igUserId, string $content, array $mediaUrls, string $token): string
    {
        $params = [
            'caption' => $content,
            'access_token' => $token,
        ];

        if (empty($mediaUrls)) {
            // Text-only posts require a simple image placeholder on Instagram
            // This should not happen in practice — always generate an image
            throw new \RuntimeException('Instagram requires at least one image for posting.');
        } elseif (count($mediaUrls) === 1) {
            // Single image post
            $params['image_url'] = $mediaUrls[0];
        } else {
            // Carousel post
            return $this->createCarouselContainer($igUserId, $content, $mediaUrls, $token);
        }

        $response = $this->client->post("{$igUserId}/media", ['form_params' => $params]);
        $data = json_decode((string) $response->getBody(), true);

        if (! isset($data['id'])) {
            throw new \RuntimeException('Instagram container creation failed: '.json_encode($data));
        }

        // Wait for container to be ready
        $this->waitForContainerReady($data['id'], $token);

        return $data['id'];
    }

    private function createCarouselContainer(string $igUserId, string $content, array $mediaUrls, string $token): string
    {
        // Create individual item containers
        $childIds = [];
        foreach (array_slice($mediaUrls, 0, 10) as $url) { // max 10 items
            $response = $this->client->post("{$igUserId}/media", [
                'form_params' => [
                    'image_url' => $url,
                    'is_carousel_item' => 'true',
                    'access_token' => $token,
                ],
            ]);
            $data = json_decode((string) $response->getBody(), true);
            if (isset($data['id'])) {
                $childIds[] = $data['id'];
            }
        }

        if (empty($childIds)) {
            throw new \RuntimeException('Failed to create carousel children.');
        }

        // Create the carousel container
        $response = $this->client->post("{$igUserId}/media", [
            'form_params' => [
                'media_type' => 'CAROUSEL',
                'children' => implode(',', $childIds),
                'caption' => $content,
                'access_token' => $token,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);
        if (! isset($data['id'])) {
            throw new \RuntimeException('Carousel container creation failed: '.json_encode($data));
        }

        return $data['id'];
    }

    private function waitForContainerReady(string $containerId, string $token, int $maxWaitSeconds = 30): void
    {
        $waited = 0;
        while ($waited < $maxWaitSeconds) {
            sleep(2);
            $waited += 2;

            $response = $this->client->get($containerId, [
                'query' => [
                    'fields' => 'status_code',
                    'access_token' => $token,
                ],
            ]);
            $data = json_decode((string) $response->getBody(), true);
            $status = $data['status_code'] ?? '';

            if ($status === 'FINISHED') {
                return;
            }
            if ($status === 'ERROR') {
                throw new \RuntimeException('Instagram container processing failed.');
            }
        }
    }

    private function getPermalink(string $mediaId, string $token): ?string
    {
        try {
            $response = $this->client->get($mediaId, [
                'query' => ['fields' => 'permalink', 'access_token' => $token],
            ]);
            $data = json_decode((string) $response->getBody(), true);
            return $data['permalink'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function validateToken(SocialConnection $connection): bool
    {
        try {
            $response = $this->client->get('me', [
                'query' => ['access_token' => $connection->access_token],
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    public function refreshToken(SocialConnection $connection): array
    {
        // Instagram uses Facebook's long-lived tokens
        $response = $this->client->get('oauth/access_token', [
            'query' => [
                'grant_type' => 'ig_exchange_token',
                'client_secret' => config('services.instagram.client_secret'),
                'access_token' => $connection->access_token,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => null,
            'expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : now()->addDays(60),
        ];
    }

    public function getAccounts(SocialConnection $connection): array
    {
        // Get Facebook pages first, then their connected IG Business Accounts
        $response = $this->client->get('me/accounts', [
            'query' => [
                'access_token' => $connection->access_token,
                'fields' => 'instagram_business_account{id,name,username,profile_picture_url,followers_count}',
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);
        $accounts = [];

        foreach ($data['data'] ?? [] as $page) {
            $ig = $page['instagram_business_account'] ?? null;
            if ($ig) {
                $accounts[] = [
                    'id' => $ig['id'],
                    'name' => $ig['name'] ?? $ig['username'] ?? 'Instagram Business',
                    'type' => 'business',
                    'url' => isset($ig['username']) ? "https://www.instagram.com/{$ig['username']}" : null,
                    'metadata' => [
                        'username' => $ig['username'] ?? null,
                        'followers_count' => $ig['followers_count'] ?? 0,
                        'avatar_url' => $ig['profile_picture_url'] ?? null,
                    ],
                ];
            }
        }

        return $accounts;
    }
}
