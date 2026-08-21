<?php

namespace App\Http\Requests\Ai;

use App\Enums\WorkspaceRole;
use App\Models\ScheduleEvent;
use App\Models\Workspace;
use App\Services\DepartmentWorkspaceService;

class DraftRequesterMeetingSummaryRequest extends DraftMeetingSummaryRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');
        if (! $workspace instanceof Workspace || ! $workspace->memberships()->active()
            ->where('user_id', $this->user()->id)
            ->where('role', WorkspaceRole::REQUESTER->value)
            ->exists()) {
            return false;
        }

        $event = ScheduleEvent::query()
            ->where('workspace_id', app(DepartmentWorkspaceService::class)->deliveryWorkspace()->id)
            ->where('public_id', $this->route('event'))
            ->first();

        return $event !== null && ($event->creator_id === $this->user()->id
            || $event->attendees()->where('users.id', $this->user()->id)->exists());
    }
}
