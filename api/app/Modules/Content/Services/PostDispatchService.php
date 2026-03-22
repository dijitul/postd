<?php

namespace App\Modules\Content\Services;

use App\Models\Post;
use App\Models\PostAttempt;
use App\Modules\Social\Platforms\FacebookPlatform;
use App\Modules\Social\Platforms\GoogleBusinessProfilePlatform;
use App\Modules\Social\Platforms\InstagramPlatform;
use App\Modules\Social\Platforms\LinkedInPlatform;
use App\Modules\Social\Platforms\TikTokPlatform;
use App\Modules\Social\Platforms\TwitterPlatform;
use App\Modules\Social\Contracts\SocialPlatformInterface;
use Illuminate\Support\Facades\Log;

class PostDispatchService
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
     * Dispatch a post to its target platform.
     * Records every attempt and updates post status accordingly.
     */
    public function dispatch(Post $post): bool
    {
        $platform = $this->platformMap[$post->platform] ?? null;

        if (! $platform) {
            Log::error("PostDispatchService: Unknown platform '{$post->platform}' for post {$post->id}");
            $post->markFailed("Unknown platform: {$post->platform}");
            return false;
        }

        $connection = $post->connection;
        if (! $connection || ! $connection->is_active) {
            $post->markFailed('Social connection not found or inactive.');
            return false;
        }

        if ($connection->isExpired()) {
            // Try to refresh the token before giving up
            try {
                app(\App\Modules\Social\Services\SocialConnectionService::class)->refreshToken($connection);
                $connection->refresh();
            } catch (\Throwable $e) {
                $post->markFailed('Social platform token has expired and could not be refreshed.');
                return false;
            }
        }

        $startTime = microtime(true);

        try {
            $result = $platform->publishPost($connection, $post->getEffectiveContent(), $post->media_urls ?? []);
            $durationMs = (microtime(true) - $startTime) * 1000;

            // Record the successful attempt
            PostAttempt::create([
                'post_id' => $post->id,
                'attempted_at' => now(),
                'succeeded' => true,
                'response_code' => $result['status_code'] ?? 200,
                'response_body' => json_encode($result),
                'duration_ms' => round($durationMs, 2),
            ]);

            $post->markPosted(
                $result['platform_post_id'] ?? 'unknown',
                $result['post_url'] ?? null
            );

            $connection->markSuccessfulUse();

            Log::info("PostDispatchService: Successfully posted to {$post->platform}", [
                'post_id' => $post->id,
                'platform_post_id' => $result['platform_post_id'] ?? null,
            ]);

            return true;

        } catch (\Throwable $e) {
            $durationMs = (microtime(true) - $startTime) * 1000;

            $errorType = $this->classifyError($e);

            PostAttempt::create([
                'post_id' => $post->id,
                'attempted_at' => now(),
                'succeeded' => false,
                'response_code' => method_exists($e, 'getCode') ? $e->getCode() : null,
                'response_body' => $e->getMessage(),
                'error_type' => $errorType,
                'duration_ms' => round($durationMs, 2),
            ]);

            $post->markFailed($e->getMessage());
            $connection->markError($e->getMessage());

            Log::error("PostDispatchService: Failed to post to {$post->platform}", [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
                'error_type' => $errorType,
            ]);

            // Send notification if this is a terminal failure
            if (! $post->canRetry()) {
                $this->notifyPostFailed($post);
            }

            return false;
        }
    }

    private function classifyError(\Throwable $e): string
    {
        $message = strtolower($e->getMessage());

        return match (true) {
            str_contains($message, 'rate limit') || str_contains($message, 'too many requests') => 'rate_limit',
            str_contains($message, 'token') || str_contains($message, 'auth') || str_contains($message, '401') => 'auth_error',
            str_contains($message, 'content') || str_contains($message, 'policy') || str_contains($message, '403') => 'content_policy',
            str_contains($message, 'network') || str_contains($message, 'connection') || str_contains($message, 'timeout') => 'network',
            default => 'unknown',
        };
    }

    private function notifyPostFailed(Post $post): void
    {
        try {
            $user = $post->business->user;
            if ($user && $post->business->settings?->notify_post_failed) {
                $user->notify(new \App\Modules\Notifications\PostFailedNotification($post));
            }
        } catch (\Throwable $e) {
            Log::error("PostDispatchService: Failed to send failure notification", ['error' => $e->getMessage()]);
        }
    }
}
