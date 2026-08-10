<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Messaging\CreateConversation;
use App\Http\Requests\Messaging\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('viewAny', [Conversation::class, $workspace]);
        $user = $request->user();
        $conversations = Conversation::query()
            ->visibleTo($user)
            ->where('workspace_id', $workspace->id)
            ->with(['project', 'participants', 'participantRecords.lastReadMessage', 'latestMessage.sender', 'latestMessage.attachments'])
            ->when($request->string('search')->toString(), function (Builder $query, string $search) {
                $query->where(function (Builder $match) use ($search) {
                    $match->where('title', 'like', '%'.$search.'%')
                        ->orWhereHas('participants', fn (Builder $participant) => $participant
                            ->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%'));
                });
            })
            ->orderByRaw('last_message_at IS NULL')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        $conversations->each(function (Conversation $conversation) use ($user) {
            $lastReadId = $conversation->participantRecords->firstWhere('user_id', $user->id)?->last_read_message_id ?? 0;
            $conversation->setAttribute('unread_count', $conversation->messages()
                ->where('id', '>', $lastReadId)
                ->where('sender_id', '!=', $user->id)
                ->count());
        });

        return ConversationResource::collection($conversations)->response();
    }

    public function store(StoreConversationRequest $request, Workspace $workspace, CreateConversation $createConversation): JsonResponse
    {
        $conversation = $createConversation->handle($workspace, $request->user(), $request->validated())
            ->load(['project', 'participants', 'participantRecords.lastReadMessage', 'latestMessage.sender']);
        $conversation->setAttribute('unread_count', 0);

        return (new ConversationResource($conversation))->response()->setStatusCode(201);
    }
}
