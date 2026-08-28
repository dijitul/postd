<?php

namespace App\Console\Commands;

use App\Models\SocialConnection;
use App\Modules\Social\Services\SocialConnectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshSocialTokensCommand extends Command
{
    protected $signature = 'social:refresh-tokens
        {--days=7 : Refresh tokens expiring within this many days}
        {--hours= : Refresh tokens expiring within this many hours (overrides --days)}
        {--dry-run : Show what would be refreshed without doing it}';

    protected $description = 'Refresh social platform tokens that are expiring, or have already expired';

    public function handle(SocialConnectionService $connectionService): int
    {
        // Google access tokens live an hour and Twitter's about two, so the hourly
        // run passes a short window to catch those. A wide window on an hourly
        // schedule would re-refresh every connection every hour, and the GBP API
        // has little quota to waste on that.
        $hours = $this->option('hours');
        $window = $hours !== null ? (float) $hours / 24 : (float) $this->option('days');
        $label = $hours !== null ? "{$hours} hours" : $this->option('days').' days';

        $this->info("Checking for tokens expired or expiring within {$label}...");

        $expiring = SocialConnection::with('business.user')
            ->where('is_active', true)
            ->expiringWithinDays($window)
            ->get();

        if ($expiring->isEmpty()) {
            $this->info('No tokens need refreshing.');
            return Command::SUCCESS;
        }

        $this->info("Found {$expiring->count()} tokens to refresh.");

        foreach ($expiring as $connection) {
            $this->line("  {$connection->platform} — {$connection->business->name} (expires {$connection->expires_at->diffForHumans()})");

            if ($this->option('dry-run')) {
                continue;
            }

            if (! $connection->refresh_token) {
                $this->warn("  -> No refresh token available. User must reconnect.");
                // Notify user
                if ($connection->business->settings?->notify_token_expiring) {
                    try {
                        $connection->business->user->notify(
                            new \App\Modules\Notifications\TokenExpiringNotification($connection)
                        );
                    } catch (\Throwable $e) {
                        Log::error('RefreshSocialTokensCommand: Failed to notify', ['error' => $e->getMessage()]);
                    }
                }
                continue;
            }

            try {
                $connectionService->refreshToken($connection);
                $this->info("  -> Refreshed successfully.");
            } catch (\Throwable $e) {
                $this->error("  -> Refresh failed: {$e->getMessage()}");
                Log::error("RefreshSocialTokensCommand: Failed to refresh token for {$connection->platform}", [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Done.');
        return Command::SUCCESS;
    }
}
