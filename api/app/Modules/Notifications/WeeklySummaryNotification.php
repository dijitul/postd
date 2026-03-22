<?php

namespace App\Modules\Notifications;

use App\Models\Business;
use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklySummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Business $business
    ) {
        $this->onQueue('critical');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $postsThisWeek = Post::where('business_id', $this->business->id)
            ->where('status', Post::STATUS_POSTED)
            ->where('posted_at', '>=', now()->subWeek())
            ->count();

        $pendingApproval = Post::where('business_id', $this->business->id)
            ->where('status', Post::STATUS_PENDING)
            ->count();

        $scheduledAhead = Post::where('business_id', $this->business->id)
            ->where('status', Post::STATUS_SCHEDULED)
            ->count();

        $connectedPlatforms = count($this->business->connectedPlatforms());

        return (new MailMessage)
            ->subject("Your weekly postd.uk summary — {$this->business->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Here's what postd.uk has been up to for {$this->business->name} this week.")
            ->line("**{$postsThisWeek}** posts published across {$connectedPlatforms} platforms")
            ->when($pendingApproval > 0, fn ($m) =>
                $m->line("**{$pendingApproval}** posts waiting for your approval in your inbox")
            )
            ->line("**{$scheduledAhead}** posts scheduled and ready to go")
            ->action('View your dashboard', config('app.frontend_url').'/posts')
            ->when($pendingApproval > 0, fn ($m) =>
                $m->action('Review posts now', config('app.frontend_url').'/posts/inbox')
            )
            ->line("Keep it up! Consistent posting is what builds your online presence.")
            ->salutation("The postd.uk team");
    }
}
