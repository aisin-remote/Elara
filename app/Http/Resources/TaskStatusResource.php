<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'color' => $this->color,
            'category' => $this->category->value,
            'position' => $this->position,
            'is_system' => $this->is_system,
        ];
    }
}
