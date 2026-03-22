<?php

namespace App\Modules\Content\Jobs;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateWeeklyContentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('generation');
    }

    /**
     * Dispatch individual GeneratePostsJob for each active, onboarded business.
     * Staggered to avoid hammering the OpenAI API simultaneously.
     */
    public function handle(): void
    {
        $businesses = Business::query()
            ->where('onboarding_complete', true)
            ->where('is_active', true)
            ->whereHas('activeSocialConnections')
            ->whereHas('user', fn ($q) => $q->where(fn ($u) =>
                $u->whereHas('subscriptions', fn ($s) => $s->active())
                  ->orWhere('trial_ends_at', '>', now())
            ))
            ->get();

        Log::info("GenerateWeeklyContentJob: Dispatching generation for {$businesses->count()} businesses");

        $delay = 0;
        foreach ($businesses as $business) {
            GeneratePostsJob::dispatch($business)
                ->onQueue('generation')
                ->delay(now()->addSeconds($delay));

            $delay += 30; // stagger each business by 30 seconds
        }
    }
}
