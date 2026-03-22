<?php

namespace App\Modules\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
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
        $trialEndDate = $this->user->trial_ends_at?->format('j F Y');

        return (new MailMessage)
            ->subject('Welcome to postd.uk — your posts are coming!')
            ->greeting("Hi {$this->user->name},")
            ->line("Welcome to postd.uk! We're genuinely excited to have you on board.")
            ->line("Your 14-day free trial of our Growth plan is now live. No card needed, and you can cancel any time.")
            ->line("Here's what happens next:")
            ->line("**1.** We're already analysing your website and Google Reviews in the background.")
            ->line("**2.** Your first AI-generated posts will appear in your inbox within the next hour.")
            ->line("**3.** Review them, approve the ones you love, and they'll be posted automatically at the perfect time.")
            ->action('Go to your inbox', config('app.frontend_url').'/posts/inbox')
            ->line("Your trial runs until {$trialEndDate}. After that, plans start from just £19/mo.")
            ->line("Any questions? Just reply to this email — we're a small UK team and we actually read them.")
            ->salutation("The postd.uk team (built by Dijitul, Mansfield)");
    }
}
