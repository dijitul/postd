<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoApprovePostsCommand extends Command
{
    protected $signature = 'posts:auto-approve {--dry-run}';
    protected $description = 'Release pending posts for businesses running in fully automatic mode';

    /**
     * Approve and schedule the pending backlog for fully automatic businesses.
     *
     * This command used to ignore auto_approve_posts entirely and approve every
     * business's pending posts once they were older than approval_window_hours.
     * That made the "fully automatic" switch meaningless in both directions: a
     * business that wanted to review its posts had them published unreviewed a
     * day later anyway, and a business that wanted automation got a day's delay
     * on every post for no reason.
     *
     * Posts generated while the switch is on are already scheduled at creation,
     * so what is left here is the backlog: everything that piled up as pending
     * before someone turned automation on. Those are released on sight rather
     * than after a window, because a post held back for a day would sail past
     * the slot it was written for.
     */
    public function handle(): int
    {
        $approved = 0;

        Business::where('onboarding_complete', true)
            ->whereHas('settings', fn ($q) => $q->where('auto_approve_posts', true))
            ->chunk(100, function ($businesses) use (&$approved) {
                foreach ($businesses as $business) {
                    $approved += $this->releaseBacklog($business);
                }
            });

        $this->info("Approved and scheduled {$approved} posts.");

        return Command::SUCCESS;
    }

    private function releaseBacklog(Business $business): int
    {
        $pendingPosts = Post::where('business_id', $business->id)
            ->where('status', Post::STATUS_PENDING)
            ->get();

        $count = 0;

        foreach ($pendingPosts as $post) {
            if ($this->option('dry-run')) {
                $this->line("Would approve and schedule post {$post->id} on {$post->platform}");
                $count++;
                continue;
            }

            $post->approve();

            // A post with no slot would sit approved and never publish, since the
            // dispatcher only ever looks at scheduled posts with a due time.
            if ($post->scheduled_at) {
                $post->schedule($post->scheduled_at);

                $count++;

                Log::info("AutoApprovePostsCommand: Released post {$post->id}", [
                    'business_id' => $business->id,
                    'platform' => $post->platform,
                    'scheduled_at' => $post->scheduled_at->toIso8601String(),
                ]);

                continue;
            }

            Log::warning("AutoApprovePostsCommand: Post {$post->id} has no scheduled_at, left approved", [
                'business_id' => $business->id,
                'platform' => $post->platform,
            ]);
        }

        return $count;
    }
}
