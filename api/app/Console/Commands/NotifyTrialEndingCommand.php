<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Notifications\TrialEndingNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyTrialEndingCommand extends Command
{
    protected $signature = 'billing:notify-trial-ending';
    protected $description = 'Send trial ending notifications to users whose trial expires in 3 days';

    public function handle(): int
    {
        $targetDate = now()->addDays(3)->toDateString();

        $users = User::onTrial()
            ->whereDate('trial_ends_at', $targetDate)
            ->whereDoesntHave('subscriptions', fn ($q) => $q->active())
            ->get();

        $this->info("Found {$users->count()} users with trials ending in 3 days.");

        foreach ($users as $user) {
            try {
                $user->notify(new TrialEndingNotification($user, 3));
                $this->line("  Notified: {$user->email}");
            } catch (\Throwable $e) {
                $this->error("  Failed to notify {$user->email}: {$e->getMessage()}");
                Log::error('NotifyTrialEndingCommand: Failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Also notify at 1 day remaining
        $users1Day = User::onTrial()
            ->whereDate('trial_ends_at', now()->addDay()->toDateString())
            ->whereDoesntHave('subscriptions', fn ($q) => $q->active())
            ->get();

        foreach ($users1Day as $user) {
            try {
                $user->notify(new TrialEndingNotification($user, 1));
                $this->line("  Notified (1 day): {$user->email}");
            } catch (\Throwable $e) {
                Log::error('NotifyTrialEndingCommand: Failed (1 day)', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Done.');
        return Command::SUCCESS;
    }
}
