<?php

namespace App\Http\Controllers\InternalApi;

use App\Events\MessageReactionChanged;
use App\Http\Requests\Messaging\ToggleReactionRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MessageReactionController extends Controller
{
    public function store(ToggleReactionRequest $request, Message $message): JsonResponse
    {
        DB::transaction(function () use ($request, $message) {
            $reaction = $message->reactions()
                ->where('user_id', $request->user()->id)
                ->where('emoji', $request->string('emoji')->toString())
                ->first();

            $reaction
                ? $reaction->delete()
                : $message->reactions()->create(['user_id' => $request->user()->id, 'emoji' => $request->string('emoji')->toString()]);
        });

        $message->load(['sender', 'attachments', 'reactions.user', 'conversation.participantRecords.user']);
        event(new MessageReactionChanged($message));

        return (new MessageResource($message))->response();
    }
}
