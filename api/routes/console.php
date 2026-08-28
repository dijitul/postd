<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled tasks ────────────────────────────────────────────────────────

// Heartbeat for the admin health tab. If this stops updating, the crontab
// entry has died and nothing else on this list is running either.
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::put('scheduler_heartbeat', now()->toIso8601String(), 3600))
    ->everyMinute()
    ->name('scheduler-heartbeat');

// Dispatch scheduled posts — runs every minute to check for posts due to go out
Schedule::job(new \App\Modules\Schedule\Jobs\DispatchScheduledPostsJob, 'posting')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->name('dispatch-scheduled-posts');

// Generate daily content for all active businesses — runs at 2am every day
Schedule::job(new \App\Modules\Content\Jobs\GenerateWeeklyContentJob, 'generation')
    ->dailyAt('02:00')
    ->withoutOverlapping(60)
    ->name('generate-daily-content');

// Scrape websites for updated info — runs daily at 3am
// Dispatches one ScrapeBusinessJob per active business into the scraping queue
Schedule::call(function () {
    \App\Models\Business::where('is_active', true)->each(function ($business) {
        dispatch(new \App\Modules\Scraping\Jobs\ScrapeBusinessJob($business));
    });
})
    ->name('scrape-business-websites')
    ->dailyAt('03:00')
    ->withoutOverlapping(120);

// Keep short-lived access tokens warm. Google's last an hour and Twitter's about
// two, so a daily sweep left them expired for most of the day. The narrow window
// means this only touches connections actually near expiry, rather than
// re-refreshing everything every hour and burning GBP quota.
Schedule::command('social:refresh-tokens --hours=2')
    ->hourly()
    ->withoutOverlapping()
    ->name('refresh-social-tokens-hourly');

// Daily sweep for the long-lived tokens, and to notify anyone whose connection
// has no refresh token left and genuinely needs reconnecting.
Schedule::command('social:refresh-tokens --days=7')
    ->daily()
    ->at('02:00')
    ->withoutOverlapping()
    ->name('refresh-social-tokens');

// Send trial-ending notifications (3 days before expiry)
Schedule::command('billing:notify-trial-ending')
    ->daily()
    ->at('09:00')
    ->name('notify-trial-ending');

// Auto-approve posts that have been pending beyond the approval window
Schedule::command('posts:auto-approve')
    ->hourly()
    ->withoutOverlapping()
    ->name('auto-approve-posts');

// Send weekly summary emails to all active businesses
Schedule::command('notifications:weekly-summary')
    ->weekly()
    ->mondays()
    ->at('08:00')
    ->name('weekly-summary-emails');

// Prune old webhook and health log records
Schedule::command('model:prune')
    ->daily()
    ->at('04:00');
