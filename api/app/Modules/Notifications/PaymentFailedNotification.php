<?php

namespace App\Modules\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
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
            ->subject('Action needed: postd.uk payment failed')
            ->greeting("Hi {$this->user->name},")
            ->line("We weren't able to process your latest payment for postd.uk.")
            ->line("This can happen if a card has expired or there are insufficient funds. Your posting will continue for now, but we'll need this resolved soon.")
            ->action('Update payment details', config('app.frontend_url').'/settings/billing')
            ->line("Alternatively, you can manage your payment details via the Stripe billing portal.")
            ->line("If you think this is an error, please don't hesitate to get in touch.")
            ->salutation("The postd.uk team");
    }
}
