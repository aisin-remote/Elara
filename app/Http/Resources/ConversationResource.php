<?php

namespace App\Http\Resources;

use App\Enums\ConversationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $participant = $this->relationLoaded('participantRecords')
            ? $this->participantRecords->firstWhere('user_id', $request->user()?->id)
            : null;
        $other = $this->relationLoaded('participants')
            ? $this->participants->firstWhere('id', '!=', $request->user()?->id)
            : null;

        return [
            'public_id' => $this->public_id,
            'type' => $this->type->value,
            'title' => $this->type === ConversationType::DIRECT ? ($other?->name ?? 'Direct message') : $this->title,
            'project' => $this->project ? ['public_id' => $this->project->public_id, 'name' => $this->project->name] : null,
            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(fn ($user) => [
                'public_id' => $user->public_id,
                'name' => $user->name,
            ])),
            'last_message' => $this->latestMessage ? [
                'body' => $this->latestMessage->body ?: ($this->latestMessage->attachments()->exists() ? 'Sent an attachment' : ''),
                'sender_name' => $this->latestMessage->sender?->name,
                'created_at' => $this->latestMessage->created_at?->toIso8601String(),
            ] : null,
            'unread_count' => (int) ($this->unread_count ?? 0),
            'last_read_message_public_id' => $participant?->lastReadMessage?->public_id,
            'muted_until' => $participant?->muted_until?->toIso8601String(),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
        ];
    }
}
