<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoApprovePostsCommand extends Command
{
    protected $signature = 'posts:auto-approve {--dry-run}';
    protected $description = 'Auto-approve posts that have been pending beyond the configured approval window';

    public function handle(): int
    {
        $approved = 0;

        Business::where('onboarding_complete', true)
            ->with('settings')
            ->chunk(100, function ($businesses) use (&$approved) {
                foreach ($businesses as $business) {
                    $settings = $business->settings;
                    if (! $settings) {
                        continue;
                    }

                    $windowHours = $settings->approval_window_hours ?? 24;
                    $cutoff = now()->subHours($windowHours);

                    $pendingPosts = Post::where('business_id', $business->id)
                        ->where('status', Post::STATUS_PENDING)
                        ->where('requires_approval', true)
                        ->where('created_at', '<=', $cutoff)
                        ->get();

                    foreach ($pendingPosts as $post) {
                        if ($this->option('dry-run')) {
                            $this->line("Would auto-approve post {$post->id} on {$post->platform}");
                            $approved++;
                            continue;
                        }

                        $post->approve();

                        if ($post->scheduled_at) {
                            $post->schedule($post->scheduled_at);
                        }

                        $approved++;

                        Log::info("AutoApprovePostsCommand: Auto-approved post {$post->id}", [
                            'business_id' => $business->id,
                            'platform' => $post->platform,
                        ]);
                    }
                }
            });

        $this->info("Auto-approved {$approved} posts.");
        return Command::SUCCESS;
    }
}
