<?php

namespace App\Console\Commands;

use App\Models\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WeeklySummaryCommand extends Command
{
    protected $signature = 'notifications:weekly-summary';
    protected $description = 'Send weekly summary emails to all active business owners';

    public function handle(): int
    {
        $businesses = Business::with(['user', 'posts'])
            ->where('onboarding_complete', true)
            ->where('is_active', true)
            ->whereHas('user', fn ($q) =>
                $q->whereHas('subscriptions', fn ($s) => $s->active())
                  ->orWhere('trial_ends_at', '>', now())
            )
            ->get();

        $this->info("Sending weekly summaries to {$businesses->count()} businesses.");

        foreach ($businesses as $business) {
            if (! $business->settings?->notify_weekly_summary) {
                continue;
            }

            try {
                $business->user->notify(
                    new \App\Modules\Notifications\WeeklySummaryNotification($business)
                );
            } catch (\Throwable $e) {
                Log::error("WeeklySummaryCommand: Failed for business {$business->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Done.');
        return Command::SUCCESS;
    }
}
