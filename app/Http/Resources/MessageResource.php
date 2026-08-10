<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $reactions = $this->whenLoaded('reactions', fn () => $this->reactions
            ->groupBy('emoji')
            ->map(fn ($items, $emoji) => [
                'emoji' => $emoji,
                'count' => $items->count(),
                'reacted' => $user ? $items->contains('user_id', $user->id) : false,
                'people' => $items->pluck('user.name')->filter()->values(),
            ])->values());
        $readBy = $this->relationLoaded('conversation') && $this->conversation->relationLoaded('participantRecords')
            ? $this->conversation->participantRecords
                ->filter(fn ($participant) => $participant->user_id !== $this->sender_id && $participant->last_read_message_id >= $this->id)
                ->pluck('user.name')->filter()->values()
            : collect();

        return [
            'public_id' => $this->public_id,
            'conversation_public_id' => $this->conversation?->public_id,
            'body' => $this->body,
            'sender' => [
                'public_id' => $this->sender?->public_id,
                'name' => $this->sender?->name,
            ],
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($file) => [
                'public_id' => $file->public_id,
                'name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'download_url' => route('internal.files.download', $file),
                'preview_url' => $file->isPreviewable() ? route('internal.files.preview', $file) : null,
            ])),
            'reactions' => $reactions,
            'read_by' => $readBy,
            'is_own' => $user?->id === $this->sender_id,
            'can_edit' => $user?->can('update', $this->resource) ?? false,
            'can_delete' => $user?->can('delete', $this->resource) ?? false,
            'edited_at' => $this->edited_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
