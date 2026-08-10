<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'title' => $this->title,
            'description' => $this->description,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'color' => $this->color,
            'meeting_url' => $this->meeting_url,
            'version' => $this->version,
            'project' => $this->project ? [
                'public_id' => $this->project->public_id,
                'name' => $this->project->name,
            ] : null,
            'attendees' => $this->attendees->map(fn ($user) => [
                'public_id' => $user->public_id,
                'name' => $user->name,
            ]),
        ];
    }
}
