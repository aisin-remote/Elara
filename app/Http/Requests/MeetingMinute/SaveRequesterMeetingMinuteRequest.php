<?php

namespace App\Http\Requests\MeetingMinute;

use App\Enums\ProjectType;
use App\Enums\WorkspaceRole;
use App\Models\MeetingMinute;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\ScheduleEvent;
use App\Models\Workspace;
use App\Services\DepartmentWorkspaceService;
use Illuminate\Validation\Validator;

class SaveRequesterMeetingMinuteRequest extends SaveMeetingMinuteRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        if (! $workspace instanceof Workspace || ! $workspace->memberships()->active()
            ->where('user_id', $this->user()->id)->where('role', WorkspaceRole::REQUESTER->value)->exists()) {
            return false;
        }

        if ($this->route('meetingMinute')) {
            $minute = $this->requestedMinute();

            return $minute !== null && $minute->creator_id === $this->user()->id && $this->user()->can('update', $minute);
        }

        return $this->canUseScheduleEventPublicId((string) $this->route('event'));
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            $expected = $this->route('event') ?: $this->requestedMinute()?->scheduleEvent?->public_id;
            if ($this->input('schedule_event_public_id') !== $expected) {
                $validator->errors()->add('schedule_event_public_id', 'The MOM must stay linked to the selected meeting.');
            }
        });
    }

    protected function meetingWorkspace(): ?Workspace
    {
        return app(DepartmentWorkspaceService::class)->deliveryWorkspace();
    }

    protected function canUseScheduleEvent(ScheduleEvent $event): bool
    {
        return $event->creator_id === $this->user()->id
            || $event->attendees()->where('users.id', $this->user()->id)->exists();
    }

    protected function canUseProject(Project $project): bool
    {
        if ($project->type === ProjectType::SYSTEM || ! config('organization.required')) {
            return true;
        }

        $departmentId = $this->route('workspace')?->organization_department_id;

        return $departmentId && ProjectRequest::query()
            ->where('workspace_id', $project->workspace_id)
            ->where('requester_department_external_id', $departmentId)
            ->where('project_id', $project->id)
            ->exists();
    }

    private function canUseScheduleEventPublicId(string $publicId): bool
    {
        $event = ScheduleEvent::query()
            ->where('workspace_id', $this->meetingWorkspace()?->id)
            ->where('public_id', $publicId)
            ->first();

        return $event !== null && $this->canUseScheduleEvent($event);
    }

    private function requestedMinute(): ?MeetingMinute
    {
        return MeetingMinute::query()
            ->where('workspace_id', $this->meetingWorkspace()?->id)
            ->where('public_id', $this->route('meetingMinute'))
            ->first();
    }
}
