<?php

namespace App\Http\Controllers\InternalApi;

use App\Events\MessageDeleted;
use App\Events\MessageUpdated;
use App\Http\Requests\Messaging\DeleteMessageRequest;
use App\Http\Requests\Messaging\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function update(UpdateMessageRequest $request, Message $message): JsonResponse
    {
        DB::transaction(fn () => $message->update([
            'body' => trim($request->string('body')->toString()),
            'edited_at' => now(),
        ]));
        $message->load(['sender', 'attachments', 'reactions.user', 'conversation.participantRecords.user']);
        event(new MessageUpdated($message));

        return (new MessageResource($message))->response();
    }

    public function destroy(DeleteMessageRequest $request, Message $message): JsonResponse
    {
        $message->load('conversation');
        DB::transaction(fn () => $message->delete());
        event(new MessageDeleted($message));

        return response()->json(['message' => 'Message deleted.']);
    }
}
