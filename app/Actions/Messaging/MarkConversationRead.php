<?php

namespace App\Actions\Messaging;

use App\Events\ConversationRead;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkConversationRead
{
    public function handle(Conversation $conversation, User $user, ?Message $message = null): ?Message
    {
        $message ??= $conversation->messages()->latest('id')->first();

        if ($message && $message->conversation_id !== $conversation->id) {
            throw ValidationException::withMessages(['message_public_id' => 'The message does not belong to this conversation.']);
        }

        DB::transaction(function () use ($conversation, $user, $message) {
            $conversation->participantRecords()->where('user_id', $user->id)->update([
                'last_read_message_id' => $message?->id,
                'updated_at' => now(),
            ]);
        });

        event(new ConversationRead($conversation, $user, $message));

        return $message;
    }
}
