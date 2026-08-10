<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public Conversation $conversation, public User $user, public ?Message $message) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('conversations.'.$this->conversation->public_id)];
    }

    public function broadcastAs(): string
    {
        return 'conversation.read';
    }

    public function broadcastWith(): array
    {
        return [
            'user' => ['public_id' => $this->user->public_id, 'name' => $this->user->name],
            'message_public_id' => $this->message?->public_id,
        ];
    }
}
