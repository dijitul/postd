<?php

namespace App\Modules\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $user,
        private readonly int $daysRemaining
    ) {
        $this->onQueue('critical');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $endDate = $this->user->trial_ends_at?->format('j F Y');
        $postsPosted = $this->user->business?->posts()->where('status', 'posted')->count() ?? 0;

        return (new MailMessage)
            ->subject("Your postd.uk trial ends in {$this->daysRemaining} days")
            ->greeting("Hi {$this->user->name},")
            ->line("Just a heads-up — your free trial ends on {$endDate}, which is {$this->daysRemaining} ".($this->daysRemaining === 1 ? 'day' : 'days')." away.")
            ->when($postsPosted > 0, fn ($m) => $m->line("In that time, we've published {$postsPosted} posts across your social channels. Not bad!"))
            ->line("To keep your posts going, just pick a plan:")
            ->line("**Starter** — £19/mo — 2 platforms + Google Business Profile")
            ->line("**Growth** — £39/mo — 4 platforms + Google Business Profile *(most popular)*")
            ->line("**Pro** — £69/mo — Everything, including TikTok video generation")
            ->line("All plans include a free Google Business Profile connection — something your competitors are probably missing.")
            ->action('Choose a plan', config('app.frontend_url').'/billing/plans')
            ->line("No card trickery. Cancel any time. UK VAT added at checkout.")
            ->salutation("The postd.uk team");
    }
}
