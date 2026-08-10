<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        return app(ConversationPolicy::class)->view($user, $message->conversation);
    }

    public function update(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id
            && $message->created_at->gte(now()->subMinutes(config('orbitra.message_edit_window_minutes', 15)))
            && app(ConversationPolicy::class)->send($user, $message->conversation);
    }

    public function delete(User $user, Message $message): bool
    {
        return $this->update($user, $message);
    }

    public function react(User $user, Message $message): bool
    {
        return app(ConversationPolicy::class)->send($user, $message->conversation);
    }
}
