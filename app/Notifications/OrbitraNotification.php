<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class OrbitraNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly array $channels,
        private readonly string $event,
        private readonly string $workspacePublicId,
        private readonly string $title,
        private readonly string $body,
        private readonly string $url,
        private readonly array $meta = [],
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return collect($this->channels)->map(fn (string $channel) => $channel === 'push' ? WebPushChannel::class : $channel)->all();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'workspace_public_id' => $this->workspacePublicId,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'meta' => $this->meta,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->body)
            ->action('Open Orbitra', $this->url);
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/favicon.ico')
            ->action('Open Orbitra', 'open_orbitra')
            ->data(['url' => $this->url, 'event' => $this->event]);
    }
}
