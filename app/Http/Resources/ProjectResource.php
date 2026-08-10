<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'workspace_public_id' => $this->workspace->public_id,
            'owner_public_id' => $this->owner->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'status' => $this->status->value,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'version' => $this->version,
            'archived_at' => $this->archived_at?->toISOString(),
        ];
    }
}
