<?php

namespace App\Modules\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Post $post
    ) {
        $this->onQueue('critical');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $platformName = match ($this->post->platform) {
            'google_business_profile' => 'Google Business Profile',
            'twitter' => 'X (Twitter)',
            default => ucfirst($this->post->platform),
        };

        $isTokenIssue = str_contains(strtolower($this->post->failure_reason ?? ''), 'token')
            || str_contains(strtolower($this->post->failure_reason ?? ''), 'auth');

        return (new MailMessage)
            ->subject("A post to {$platformName} couldn't be published")
            ->greeting("Hi,")
            ->line("We tried to publish a post to {$platformName} but ran into a problem.")
            ->line("Content preview: *\"{$this->getContentPreview()}\"*")
            ->when($isTokenIssue, fn ($m) =>
                $m->line("It looks like your {$platformName} connection may need to be refreshed. This can happen when platform permissions expire.")
            )
            ->when(! $isTokenIssue, fn ($m) =>
                $m->line("Error: {$this->post->failure_reason}")
            )
            ->action('View post', config('app.frontend_url').'/posts/'.$this->post->id)
            ->line("You can retry the post from your dashboard, or reconnect your {$platformName} account if needed.")
            ->line("We'll keep an eye on things from our end too.")
            ->salutation("The postd.uk team");
    }

    private function getContentPreview(): string
    {
        $content = $this->post->getEffectiveContent();
        return strlen($content) > 80 ? substr($content, 0, 80).'...' : $content;
    }
}
