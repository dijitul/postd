<?php

namespace App\Modules\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $user
    ) {
        $this->onQueue('critical');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your postd.uk subscription has ended')
            ->greeting("Hi {$this->user->name},")
            ->line("We're sorry to see you go. Your postd.uk subscription has now ended.")
            ->line("Your posts will stop being generated and published, but all your content history will be kept safely for 30 days.")
            ->line("If you ever want to come back, just pick a plan and we'll get started again straight away.")
            ->action('Reactivate your account', config('app.frontend_url').'/billing/plans')
            ->line("If there's anything we could have done better, please reply to this email — we read every one.")
            ->salutation("The postd.uk team (built by Dijitul, Mansfield)");
    }
}
