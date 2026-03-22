<?php

namespace App\Modules\Notifications;

use App\Models\SocialConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TokenExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SocialConnection $connection
    ) {
        $this->onQueue('critical');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $platformName = match ($this->connection->platform) {
            'google_business_profile' => 'Google Business Profile',
            'twitter' => 'X (Twitter)',
            default => ucfirst($this->connection->platform),
        };

        $daysLeft = $this->connection->expires_at
            ? now()->diffInDays($this->connection->expires_at)
            : 0;

        return (new MailMessage)
            ->subject("Action needed: Reconnect your {$platformName} account")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your {$platformName} connection is expiring in {$daysLeft} ".($daysLeft === 1 ? 'day' : 'days').".")
            ->line("To keep your posts going automatically, you'll need to reconnect your account. It only takes a moment.")
            ->action("Reconnect {$platformName}", config('app.frontend_url').'/settings/connections')
            ->line("If you don't reconnect, we'll pause posting to {$platformName} to avoid any errors.")
            ->salutation("The postd.uk team");
    }
}
