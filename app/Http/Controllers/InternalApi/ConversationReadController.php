<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Messaging\MarkConversationRead;
use App\Http\Requests\Messaging\MarkConversationReadRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ConversationReadController extends Controller
{
    public function store(MarkConversationReadRequest $request, Conversation $conversation, MarkConversationRead $markRead): JsonResponse
    {
        $message = $request->filled('message_public_id')
            ? $conversation->messages()->where('public_id', $request->string('message_public_id')->toString())->first()
            : null;

        if ($request->filled('message_public_id') && ! $message) {
            throw ValidationException::withMessages(['message_public_id' => 'The message does not belong to this conversation.']);
        }

        $message = $markRead->handle($conversation, $request->user(), $message);

        return response()->json([
            'data' => ['last_read_message_public_id' => $message?->public_id],
            'message' => 'Conversation marked as read.',
        ]);
    }
}
