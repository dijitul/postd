<?php

namespace App\Modules\Schedule\Jobs;

use App\Models\Post;
use App\Modules\Content\Services\PostDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PostPublishJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 90;
    public int $tries = 3;

    /**
     * Exponential backoff for retries: 30s, 5min, 30min.
     */
    public function backoff(): array
    {
        return [30, 300, 1800];
    }

    public function __construct(
        public readonly Post $post
    ) {
        $this->onQueue('posting');
    }

    public function handle(PostDispatchService $dispatchService): void
    {
        $post = $this->post->fresh();

        // Safety checks
        if (! $post || in_array($post->status, [Post::STATUS_POSTED, Post::STATUS_REJECTED, Post::STATUS_FAILED])) {
            Log::info("PostPublishJob: Skipping post {$this->post->id} — status is {$post?->status}");
            return;
        }

        // Check the business still has an active subscription or trial
        $user = $post->business->user;
        if (! $user->hasActivePlan()) {
            $post->update([
                'status' => Post::STATUS_FAILED,
                'failure_reason' => 'Account subscription has expired.',
            ]);
            Log::warning("PostPublishJob: Skipping post {$post->id} — subscription expired for user {$user->id}");
            return;
        }

        $dispatchService->dispatch($post);
    }

    public function failed(\Throwable $exception): void
    {
        $post = $this->post->fresh();

        if ($post && ! in_array($post->status, [Post::STATUS_POSTED, Post::STATUS_REJECTED])) {
            $post->markFailed('Job failed after all retries: '.$exception->getMessage());
        }

        Log::error("PostPublishJob: Failed for post {$this->post->id}", [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
