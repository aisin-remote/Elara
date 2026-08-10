<?php

namespace App\Http\Requests\Schedule;

use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

abstract class ScheduleEventMutationRequest extends FormRequest
{
    protected function eventRules(Workspace $workspace): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'meeting_url' => ['nullable', 'url:http,https', 'max:2048'],
            'project_public_id' => ['nullable', 'string', 'size:26', $this->projectRule($workspace)],
            'attendee_public_ids' => ['nullable', 'array', 'max:100'],
            'attendee_public_ids.*' => ['string', 'size:26', 'distinct', $this->attendeeRule($workspace)],
        ];
    }

    private function projectRule(Workspace $workspace): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($workspace): void {
            if (! $value) {
                return;
            }

            $project = Project::query()->where('workspace_id', $workspace->id)->where('public_id', $value)->first();

            if (! $project || ! $this->user()->can('create', [Task::class, $project])) {
                $fail('Choose a project you can schedule.');
            }
        };
    }

    private function attendeeRule(Workspace $workspace): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($workspace): void {
            $exists = $workspace->memberships()->active()->whereHas('user', fn ($query) => $query->where('public_id', $value))->exists();

            if (! $exists) {
                $fail('Every attendee must be an active workspace member.');
            }
        };
    }
}
