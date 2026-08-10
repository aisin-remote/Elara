<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'joined_at' => $this->joined_at?->toISOString(),
            'user' => [
                'public_id' => $this->user->public_id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
        ];
    }
}
