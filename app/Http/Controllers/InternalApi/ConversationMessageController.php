<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Messaging\SendMessage;
use App\Http\Requests\Messaging\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationMessageController extends Controller
{
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $messages = $conversation->messages()
            ->with(['sender', 'attachments', 'reactions.user', 'conversation.participantRecords.user'])
            ->latest('id')
            ->cursorPaginate(min(50, max(1, $request->integer('per_page', 30))));

        return MessageResource::collection($messages)->response();
    }

    public function store(StoreMessageRequest $request, Conversation $conversation, SendMessage $sendMessage): JsonResponse
    {
        $message = $sendMessage->handle(
            $conversation,
            $request->user(),
            $request->string('body')->toString() ?: null,
            $request->file('attachments', []),
        );

        return (new MessageResource($message))->response()->setStatusCode(201);
    }
}
