<?php

namespace App\Modules\Schedule\Jobs;

use App\Models\Post;
use App\Modules\Content\Services\PostDispatchService;
use App\Modules\Schedule\Jobs\PostPublishJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchScheduledPostsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('posting');
    }

    /**
     * Find all posts due to post and dispatch them individually.
     * Runs every minute via the scheduler.
     */
    public function handle(): void
    {
        $duePosts = Post::query()
            ->with(['business', 'connection'])
            ->where('status', Post::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($duePosts->isEmpty()) {
            return;
        }

        Log::info("DispatchScheduledPostsJob: Dispatching {$duePosts->count()} due posts");

        foreach ($duePosts as $post) {
            // Update status immediately to avoid double-dispatch
            $post->update(['status' => 'dispatching']);

            PostPublishJob::dispatch($post)->onQueue('posting');
        }
    }
}
