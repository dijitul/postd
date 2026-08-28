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

        // Upload photos if provided. Graph wants each attachment as its own indexed
        // JSON string (attached_media[0]={"media_fbid":"..."}); a nested PHP array
        // form-encodes to attached_media[0][media_fbid], which Graph rejects.
        if (! empty($mediaUrls)) {
            $index = 0;
            foreach (array_slice($mediaUrls, 0, 10) as $url) {
                $photoId = $this->uploadPhoto($url, $pageId, $pageToken);
                if ($photoId) {
                    $params["attached_media[{$index}]"] = json_encode(['media_fbid' => $photoId]);
                    $index++;
                }
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

    /**
     * Exchange the user token for the Page's own access token.
     *
     * Posting to a Page requires the Page token, not the user token. We used to
     * fall back to the user token when the lookup failed, which turned a clear
     * permissions problem into a confusing "(#200) Permissions error" on publish.
     */
    private function getPageToken(string $userToken, string $pageId): string
    {
        $response = $this->client->get("{$pageId}", [
            'query' => [
                'fields' => 'access_token',
                'access_token' => $userToken,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        if (empty($data['access_token'])) {
            throw new \RuntimeException(
                'Could not obtain a Facebook Page access token. The connection is '
                .'probably missing the pages_manage_posts permission — reconnect Facebook.'
            );
        }

        return $data['access_token'];
    }

    /**
     * Resolve the Page to post to when the user has not chosen one.
     *
     * Only safe when the account manages exactly one Page. Picking the first Page
     * Facebook happens to return would post a business's content to an unrelated
     * Page it also administers, so refuse rather than guess.
     */
    private function getDefaultPageId(SocialConnection $connection): ?string
    {
        try {
            $pages = $this->getAccounts($connection);
        } catch (\Throwable $e) {
            Log::warning('FacebookPlatform: could not list Pages', ['error' => $e->getMessage()]);
            return null;
        }

        if (count($pages) === 1) {
            return $pages[0]['id'];
        }

        if (count($pages) > 1) {
            throw new \RuntimeException(
                'This Facebook account manages '.count($pages).' Pages and none has '
                .'been selected. Choose which Page to post to in Platforms.'
            );
        }

        return null;
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
