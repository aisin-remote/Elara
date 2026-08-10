<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $workspaceName,
        private readonly string $inviterName,
        private readonly string $token,
        private readonly string $expiresAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You're invited to {$this->workspaceName} on Orbitra")
            ->greeting('Join your team on Orbitra')
            ->line("{$this->inviterName} invited you to {$this->workspaceName}.")
            ->action('Review invitation', route('invitations.show', $this->token))
            ->line("This invitation expires {$this->expiresAt}.");
    }
}
