<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'workspace_public_id' => $this->workspace?->public_id,
            'project_public_id' => $this->project?->public_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'status' => $this->whenLoaded('status', fn () => new TaskStatusResource($this->status)),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'public_id' => $this->category->public_id,
                'name' => $this->category->name,
                'color' => $this->category->color,
            ] : null),
            'start_at' => $this->start_at,
            'due_at' => $this->due_at,
            'completed_at' => $this->completed_at,
            'estimate_minutes' => $this->estimate_minutes,
            'is_blocked' => $this->isBlocked(),
            'position' => $this->position,
            'version' => $this->version,
            'assignees' => $this->whenLoaded('assignees', fn () => $this->assignees->map(fn ($user) => [
                'public_id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
            ])),
            'milestone' => $this->whenLoaded('milestone', fn () => $this->milestone ? [
                'public_id' => $this->milestone->public_id,
                'name' => $this->milestone->name,
                'target_date' => $this->milestone->target_date->toDateString(),
                'completed_at' => $this->milestone->completed_at,
            ] : null),
            'dependencies' => $this->whenLoaded('dependencies', fn () => $this->dependencies->map(fn ($dependency) => [
                'public_id' => $dependency->public_id,
                'title' => $dependency->title,
                'completed_at' => $dependency->completed_at,
            ])),
            'checklist' => $this->whenLoaded('checklistItems', fn () => $this->checklistItems->map(fn ($item) => [
                'public_id' => $item->public_id,
                'title' => $item->title,
                'is_completed' => $item->is_completed,
            ])),
            'attachments' => $this->whenLoaded('files', fn () => $this->files->map(fn ($file) => [
                'public_id' => $file->public_id,
                'name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
            ])),
        ];
    }
}
