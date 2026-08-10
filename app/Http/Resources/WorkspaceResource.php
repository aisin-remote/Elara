<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'icon' => $this->icon,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'week_start' => $this->settings_json['week_start'] ?? 1,
            'owner_public_id' => $this->whenLoaded('owner', fn () => $this->owner->public_id),
        ];
    }
}
