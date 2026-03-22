<?php

namespace App\Modules\Social\Platforms;

use App\Models\SocialConnection;
use App\Modules\Social\Contracts\SocialPlatformInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class TikTokPlatform implements SocialPlatformInterface
{
    private readonly Client $client;
    private readonly Client $creatomateClient;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://open.tiktokapis.com/v2/',
            'timeout' => 60,
        ]);

        $this->creatomateClient = new Client([
            'base_uri' => config('services.creatomate.base_url', 'https://api.creatomate.com/v1/'),
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer '.config('services.creatomate.key'),
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Publish a TikTok video.
     * The content should be a script — we use Creatomate to render the video first.
     */
    public function publishPost(SocialConnection $connection, string $content, array $mediaUrls = []): array
    {
        // If a video URL is already provided, upload it directly
        if (! empty($mediaUrls) && $this->isVideoUrl($mediaUrls[0])) {
            return $this->uploadAndPublishVideo($connection, $content, $mediaUrls[0]);
        }

        // Otherwise, render a video from the script using Creatomate
        $videoUrl = $this->renderVideoFromScript($content, $connection);
        if (! $videoUrl) {
            throw new \RuntimeException('TikTok video rendering failed via Creatomate.');
        }

        return $this->uploadAndPublishVideo($connection, $content, $videoUrl);
    }

    private function uploadAndPublishVideo(SocialConnection $connection, string $caption, string $videoUrl): array
    {
        // Step 1: Initiate upload
        $initiateResponse = $this->client->post('post/publish/video/init/', [
            'headers' => $this->buildHeaders($connection),
            'json' => [
                'post_info' => [
                    'title' => substr($caption, 0, 150),
                    'privacy_level' => 'PUBLIC_TO_EVERYONE',
                    'disable_duet' => false,
                    'disable_comment' => false,
                    'disable_stitch' => false,
                ],
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'video_url' => $videoUrl,
                ],
            ],
        ]);

        $data = json_decode((string) $initiateResponse->getBody(), true);
        $publishId = $data['data']['publish_id'] ?? null;

        if (! $publishId) {
            throw new \RuntimeException('TikTok initiate upload failed: '.json_encode($data));
        }

        // Step 2: Wait for processing
        $this->waitForPublish($publishId, $connection);

        return [
            'platform_post_id' => $publishId,
            'post_url' => null, // TikTok doesn't return post URL immediately
            'status_code' => $initiateResponse->getStatusCode(),
        ];
    }

    private function waitForPublish(string $publishId, SocialConnection $connection, int $maxWait = 60): void
    {
        $waited = 0;
        while ($waited < $maxWait) {
            sleep(5);
            $waited += 5;

            $statusResponse = $this->client->post('post/publish/status/fetch/', [
                'headers' => $this->buildHeaders($connection),
                'json' => ['publish_id' => $publishId],
            ]);

            $data = json_decode((string) $statusResponse->getBody(), true);
            $status = $data['data']['status'] ?? '';

            if ($status === 'PUBLISH_COMPLETE') {
                return;
            }
            if (in_array($status, ['FAILED', 'SPAM_RISK_USER_BANNED', 'SPAM_RISK_TOO_FREQUENT_POSTS'])) {
                throw new \RuntimeException("TikTok publish failed with status: {$status}");
            }
        }

        throw new \RuntimeException('TikTok publish timed out after '.$maxWait.' seconds.');
    }

    private function renderVideoFromScript(string $script, SocialConnection $connection): ?string
    {
        if (! config('services.creatomate.key')) {
            Log::warning('TikTokPlatform: No Creatomate API key configured. Cannot render video.');
            return null;
        }

        try {
            $business = $connection->business;

            $response = $this->creatomateClient->post('renders', [
                'json' => [
                    'template_id' => config('services.creatomate.tiktok_template_id'),
                    'modifications' => [
                        'script' => $script,
                        'business_name' => $business->name,
                        'industry' => $business->industry,
                    ],
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            $renderId = $data[0]['id'] ?? null;

            if (! $renderId) {
                return null;
            }

            // Poll for completion
            $maxAttempts = 30;
            for ($i = 0; $i < $maxAttempts; $i++) {
                sleep(5);

                $statusResponse = $this->creatomateClient->get("renders/{$renderId}");
                $status = json_decode((string) $statusResponse->getBody(), true);

                if ($status['status'] === 'succeeded') {
                    return $status['url'];
                }
                if ($status['status'] === 'failed') {
                    Log::error('TikTokPlatform: Creatomate render failed', ['render_id' => $renderId]);
                    return null;
                }
            }
        } catch (\Throwable $e) {
            Log::error('TikTokPlatform: Creatomate error', ['error' => $e->getMessage()]);
        }

        return null;
    }

    public function validateToken(SocialConnection $connection): bool
    {
        try {
            $response = $this->client->post('user/info/', [
                'headers' => $this->buildHeaders($connection),
                'json' => ['fields' => ['open_id', 'display_name']],
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    public function refreshToken(SocialConnection $connection): array
    {
        $response = $this->client->post('oauth/token/', [
            'form_params' => [
                'client_key' => config('services.tiktok.client_id'),
                'client_secret' => config('services.tiktok.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);
        $tokenData = $data['data'] ?? [];

        if (! isset($tokenData['access_token'])) {
            throw new \RuntimeException('TikTok token refresh failed: '.json_encode($data));
        }

        return [
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $connection->refresh_token,
            'expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in']) : null,
        ];
    }

    public function getAccounts(SocialConnection $connection): array
    {
        try {
            $response = $this->client->post('user/info/', [
                'headers' => $this->buildHeaders($connection),
                'json' => ['fields' => ['open_id', 'display_name', 'avatar_url', 'follower_count', 'profile_deep_link']],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            $user = $data['data']['user'] ?? [];

            return [[
                'id' => $user['open_id'] ?? '',
                'name' => $user['display_name'] ?? 'TikTok Account',
                'type' => 'profile',
                'url' => $user['profile_deep_link'] ?? null,
                'metadata' => [
                    'followers_count' => $user['follower_count'] ?? 0,
                    'avatar_url' => $user['avatar_url'] ?? null,
                ],
            ]];
        } catch (\Throwable $e) {
            Log::error('TikTokPlatform: getAccounts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function buildHeaders(SocialConnection $connection): array
    {
        return [
            'Authorization' => "Bearer {$connection->access_token}",
            'Content-Type' => 'application/json; charset=UTF-8',
        ];
    }

    private function isVideoUrl(string $url): bool
    {
        $videoExtensions = ['mp4', 'mov', 'avi', 'webm'];
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return in_array($extension, $videoExtensions);
    }
}
