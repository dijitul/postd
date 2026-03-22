<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled tasks ────────────────────────────────────────────────────────

// Dispatch scheduled posts — runs every minute to check for posts due to go out
Schedule::job(new \App\Modules\Schedule\Jobs\DispatchScheduledPostsJob, 'posting')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->name('dispatch-scheduled-posts');

// Generate weekly content for all active businesses — runs Sunday midnight
Schedule::job(new \App\Modules\Content\Jobs\GenerateWeeklyContentJob, 'generation')
    ->weekly()
    ->sundays()
    ->at('00:00')
    ->withoutOverlapping(60)
    ->name('generate-weekly-content');

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

// Check social tokens expiring in 7 days and refresh them
Schedule::command('social:refresh-tokens')
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
