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
    /**
     * How long a post may sit in 'dispatching' before we assume its publish job
     * died (worker restart, queue flush) and reclaim it.
     *
     * Must exceed PostPublishJob's full retry span, or we would queue a second
     * publish job while the first is still waiting on its backoff and post twice.
     * That span is 30s + 300s + 1800s ≈ 36 minutes, so 60 leaves clear headroom.
     */
    private const STALE_DISPATCH_MINUTES = 60;

    public function handle(): void
    {
        $this->reclaimStalePosts();

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
            $post->update(['status' => Post::STATUS_DISPATCHING]);

            PostPublishJob::dispatch($post)->onQueue('posting');
        }
    }

    /**
     * Return posts orphaned in 'dispatching' to 'scheduled' so they get another go.
     *
     * A post is marked 'dispatching' here and then moved on by PostPublishJob. If
     * the worker dies in between, nothing ever queries that status again and the
     * post is stranded permanently. PostPublishJob retries for at most ~35 minutes,
     * so anything older than the window below has no job behind it any more.
     */
    private function reclaimStalePosts(): void
    {
        $cutoff = now()->subMinutes(self::STALE_DISPATCH_MINUTES);

        $stale = Post::query()
            ->where('status', Post::STATUS_DISPATCHING)
            ->where('updated_at', '<=', $cutoff)
            ->get();

        if ($stale->isEmpty()) {
            return;
        }

        foreach ($stale as $post) {
            $post->update(['status' => Post::STATUS_SCHEDULED]);
        }

        Log::warning("DispatchScheduledPostsJob: Reclaimed {$stale->count()} posts stranded in 'dispatching'");
    }
}
