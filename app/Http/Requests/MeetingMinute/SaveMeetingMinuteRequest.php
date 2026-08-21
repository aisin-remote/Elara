<?php

namespace App\Http\Requests\MeetingMinute;

use App\Enums\MeetingMinuteStatus;
use App\Models\MeetingMinute;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveMeetingMinuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $minute = $this->route('meetingMinute');

        return $minute instanceof MeetingMinute
            ? $this->user()->can('update', $minute)
            : $this->user()->can('create', [MeetingMinute::class, $this->route('workspace')]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'meeting_at' => ['required', 'date'],
            'summary' => ['nullable', 'string', 'max:20000'],
            'schedule_event_public_id' => ['nullable', 'string', 'size:26'],
            'project_public_id' => ['nullable', 'string', 'size:26'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.content' => ['required', 'string', 'max:5000'],
            'items.*.pic_name' => ['required', 'string', 'max:120'],
            'items.*.pic_user_public_id' => ['nullable', 'string', 'size:26'],
            'items.*.due_date' => ['nullable', 'date'],
            'items.*.status' => ['required', Rule::enum(MeetingMinuteStatus::class)],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:'.config('orbitra.max_file_upload_kb'), 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $workspace = $this->meetingWorkspace();
            $event = null;
            $eventPublicId = $this->input('schedule_event_public_id');

            if (filled($eventPublicId)) {
                $event = ScheduleEvent::query()
                    ->where('workspace_id', $workspace?->id)
                    ->where('public_id', $eventPublicId)
                    ->first();

                if (! $event || ! $this->canUseScheduleEvent($event)) {
                    $validator->errors()->add('schedule_event_public_id', 'Choose an accessible schedule event.');
                } else {
                    $minute = $this->route('meetingMinute');
                    $duplicate = MeetingMinute::query()
                        ->where('schedule_event_id', $event->id)
                        ->when($minute instanceof MeetingMinute, fn ($query) => $query->whereKeyNot($minute->id))
                        ->exists();

                    if ($duplicate) {
                        $validator->errors()->add('schedule_event_public_id', 'This event already has a MOM.');
                    }
                }
            }

            $projectPublicId = $this->input('project_public_id');
            if (filled($projectPublicId)) {
                $project = Project::query()
                    ->where('workspace_id', $workspace?->id)
                    ->where('public_id', $projectPublicId)
                    ->first();

                if (! $project || ! $this->canUseProject($project)) {
                    $validator->errors()->add('project_public_id', 'Choose an accessible project or system.');
                }
            }

            foreach ($this->input('items', []) as $index => $item) {
                $picPublicId = $item['pic_user_public_id'] ?? null;
                if (filled($picPublicId)) {
                    $pic = User::query()->where('public_id', $picPublicId)->first();

                    if (! $pic || ! $workspace || ! $this->canUsePic($pic, $workspace, $event)) {
                        $validator->errors()->add("items.$index.pic_user_public_id", 'Choose an available Orbitra user or enter a name as free text.');
                    }
                }
            }
        });
    }

    protected function meetingWorkspace(): ?Workspace
    {
        return $this->route('workspace') ?? $this->route('meetingMinute')?->workspace;
    }

    protected function canUseScheduleEvent(ScheduleEvent $event): bool
    {
        return $this->user()->can('view', $event);
    }

    protected function canUseProject(Project $project): bool
    {
        return true;
    }

    protected function canUsePic(User $pic, Workspace $workspace, ?ScheduleEvent $event): bool
    {
        return $workspace->memberships()->active()->where('user_id', $pic->id)->exists()
            || ($event?->attendees()->where('users.id', $pic->id)->exists() ?? false);
    }

    public function messages(): array
    {
        return [
            'attachments.*.mimes' => 'Attach an image, document, spreadsheet, text file, or zip.',
            'attachments.*.max' => 'One of the attachments is larger than this workspace allows.',
        ];
    }
}
