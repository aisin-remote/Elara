<?php

namespace App\Services;

use App\Enums\BreakdownStatus;
use App\Enums\CheckpointStatus;
use App\Enums\FeatureRequestStatus;
use App\Enums\OrganizationRankGroup;
use App\Enums\ProjectRequestStatus;
use App\Enums\TaskStatusCategory;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Models\Task;
use App\Models\TaskBreakdown;
use App\Models\User;
use App\Models\ValidationCheckpoint;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RequestProgressService
{
    public function build(FeatureRequest|ProjectRequest $request, User $viewer): array
    {
        $tasks = $this->tasks($request);
        $checkpoints = ValidationCheckpoint::query()
            ->where('subject_type', $request->getMorphClass())
            ->where('subject_id', $request->id)
            ->oldest('opened_at')
            ->get();
        $openCheckpoints = $checkpoints->where('status', CheckpointStatus::OPEN);
        $breakdown = $request->breakdowns()->latest('id')->first();
        $logs = ActivityLog::query()
            ->where('subject_type', $request->getMorphClass())
            ->where('subject_id', $request->id)
            ->oldest('created_at')
            ->get();

        $current = $request instanceof FeatureRequest
            ? $this->featureStage($request, $tasks, $checkpoints, $openCheckpoints)
            : $this->projectStage($request, $tasks, $checkpoints, $openCheckpoints);
        $stopped = in_array($request->status->value, ['rejected', 'taken_down'], true);
        $attention = $request->status->value === 'needs_info' || $openCheckpoints->isNotEmpty();
        $definitions = $request instanceof FeatureRequest
            ? $this->featureStages($request, $tasks, $checkpoints, $logs, $breakdown)
            : $this->projectStages($request, $tasks, $checkpoints, $logs, $breakdown);

        $completed = $tasks->whereNotNull('completed_at')->count();
        $blocked = $tasks->filter(fn (Task $task) => $task->completed_at === null && $task->isBlocked())->count();
        $progress = $tasks->isEmpty()
            ? ($request->status->value === 'delivered' ? 100 : 0)
            : (int) round($tasks->average(fn (Task $task) => $task->checklist_total > 0
                ? ($task->checklist_completed / $task->checklist_total) * 100
                : ($task->completed_at ? 100 : 0)));
        $timezone = $request->workspace->timezone ?: config('app.timezone');
        $updated = collect([
            $request->updated_at,
            $tasks->max('updated_at'),
            $checkpoints->max('updated_at'),
            $breakdown?->updated_at,
        ])->filter()->sortByDesc(fn (CarbonInterface $date) => $date->getTimestamp())->first();

        return [
            'current_stage' => $this->currentLabel($request, $definitions[$current]['label']),
            'description' => $this->description($request, $tasks, $openCheckpoints, $breakdown, $blocked),
            'status' => $request->status->value,
            'progress' => $progress,
            'tasks' => ['completed' => $completed, 'total' => $tasks->count(), 'blocked' => $blocked],
            'task_timeline' => $this->taskTimeline($request, $tasks, $viewer, $timezone),
            'validations' => [
                'open' => $openCheckpoints->count(),
                'deadline' => $openCheckpoints->sortBy('expires_at')->first()?->countdown(),
            ],
            'assignee' => $request->assignee?->name,
            'schedule' => $this->scheduleLabel($request, $timezone),
            'action' => $openCheckpoints->isNotEmpty()
                ? ['label' => 'Open validation', 'url' => route('desk.validations.index')]
                : null,
            'stages' => collect($definitions)->map(function (array $stage, int $index) use ($current, $stopped, $attention, $request, $timezone) {
                $isCurrent = $index === $current;
                $delivered = $request->status->value === 'delivered';

                return [
                    ...$stage,
                    'state' => $index < $current || ($delivered && $index === $current)
                        ? 'completed'
                        : ($index > $current ? 'upcoming' : ($stopped ? 'failed' : ($attention ? 'attention' : 'current'))),
                    'is_current' => $isCurrent,
                    'time' => $stage['at']?->toIso8601String(),
                    'time_label' => $stage['at']?->setTimezone($timezone)->format('j M Y, H:i'),
                ];
            })->map(fn (array $stage) => collect($stage)->except('at')->all())->values()->all(),
            'updated_at' => $updated?->toIso8601String(),
            'updated_label' => $updated?->setTimezone($timezone)->diffForHumans(),
        ];
    }

    private function tasks(FeatureRequest|ProjectRequest $request): Collection
    {
        if ($request instanceof FeatureRequest && ! $request->feature_id) {
            return collect();
        }

        if ($request instanceof ProjectRequest && ! $request->project_id) {
            return collect();
        }

        return Task::query()
            ->where($request instanceof FeatureRequest ? 'feature_id' : 'project_id', $request instanceof FeatureRequest ? $request->feature_id : $request->project_id)
            ->whereNull('archived_at')
            ->whereHas('status', fn ($status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value))
            ->with([
                'dependencies:id,completed_at',
                'status:id,name,color,category',
                'assignees:id,public_id,first_name,last_name,avatar_path',
                'files.uploader:id,public_id,first_name,last_name',
            ])
            ->withCount([
                'checklistItems as checklist_total',
                'checklistItems as checklist_completed' => fn ($items) => $items->where('is_completed', true),
            ])
            ->get();
    }

    private function taskTimeline(FeatureRequest|ProjectRequest $request, Collection $tasks, User $viewer, string $timezone): array
    {
        return $tasks->sortBy(fn (Task $task) => $task->start_at?->getTimestamp() ?? $task->due_at?->getTimestamp() ?? PHP_INT_MAX)
            ->values()
            ->map(function (Task $task) use ($request, $viewer, $timezone): array {
                $progress = $task->checklist_total > 0
                    ? (int) round($task->checklist_completed / $task->checklist_total * 100)
                    : ($task->completed_at ? 100 : 0);
                $attachments = $task->files
                    ->filter(fn ($file) => (bool) data_get($file->metadata_json, 'request_shared'))
                    ->map(fn ($file) => [
                        'name' => $file->original_name,
                        'size_label' => number_format($file->size / 1024, 1).' KB',
                        'uploader' => $file->uploader->name,
                        'url' => route('internal.files.download', $file),
                    ])->values()->all();

                return [
                    'public_id' => $task->public_id,
                    'title' => $task->title,
                    'status' => ['name' => $task->status->name, 'color' => $task->status->color],
                    'progress' => $progress,
                    'blocked' => $task->completed_at === null && $task->isBlocked(),
                    'completed' => $task->completed_at !== null,
                    'start_at' => $task->start_at?->toIso8601String(),
                    'start_label' => $task->start_at?->copy()->setTimezone($timezone)->format('j M Y'),
                    'due_at' => $task->due_at?->toIso8601String(),
                    'due_label' => $task->due_at?->copy()->setTimezone($timezone)->format('j M Y'),
                    'assignees' => $task->assignees->map(fn (User $assignee) => $assignee->name)->values()->all(),
                    'attachments' => $attachments,
                    'upload_url' => $request->requester_id === $viewer->id || $viewer->can('update', $task)
                        ? route('internal.task-attachments.store', $task)
                        : null,
                ];
            })->all();
    }

    private function featureStage(FeatureRequest $request, Collection $tasks, Collection $checkpoints, Collection $open): int
    {
        $offset = $this->requiresDepartmentApproval($request) ? 1 : 0;

        if ($request->status === FeatureRequestStatus::DELIVERED) {
            return 6 + $offset;
        }

        if ($request->status === FeatureRequestStatus::REJECTED) {
            return $request->reviewed_at ? 1 + $offset : 1;
        }

        if ($request->status === FeatureRequestStatus::TAKEN_DOWN) {
            return $checkpoints->isNotEmpty() ? 5 + $offset : ($tasks->isNotEmpty() ? 4 + $offset : 2 + $offset);
        }

        if ($open->isNotEmpty()) {
            return 5 + $offset;
        }

        if ($tasks->isNotEmpty() && $tasks->every(fn (Task $task) => $task->completed_at !== null)) {
            return 6 + $offset;
        }

        if ($tasks->isNotEmpty() || $request->status === FeatureRequestStatus::IN_PROGRESS) {
            return 4 + $offset;
        }

        return match ($request->status) {
            FeatureRequestStatus::SCHEDULED => 3 + $offset,
            FeatureRequestStatus::APPROVED => 2 + $offset,
            FeatureRequestStatus::PENDING_DEPARTMENT => 1,
            FeatureRequestStatus::PENDING_REVIEW => 1 + $offset,
            FeatureRequestStatus::NEEDS_INFO => $request->needs_info_stage === 'department' ? 1 : 1 + $offset,
            default => 0,
        };
    }

    private function projectStage(ProjectRequest $request, Collection $tasks, Collection $checkpoints, Collection $open): int
    {
        $offset = $this->requiresDepartmentApproval($request) ? 1 : 0;

        if ($request->status === ProjectRequestStatus::DELIVERED) {
            return 8 + $offset;
        }

        if ($request->status === ProjectRequestStatus::REJECTED) {
            return $request->manager_at ? 3 + $offset : ($request->meetingHeld() || $request->spv_at ? 2 + $offset : 1);
        }

        if ($request->status === ProjectRequestStatus::TAKEN_DOWN) {
            return $checkpoints->isNotEmpty() ? 7 + $offset : ($tasks->isNotEmpty() ? 6 + $offset : 4 + $offset);
        }

        if ($open->isNotEmpty()) {
            return 7 + $offset;
        }

        if ($tasks->isNotEmpty() && $tasks->every(fn (Task $task) => $task->completed_at !== null)) {
            return 8 + $offset;
        }

        if ($tasks->isNotEmpty() || $request->status === ProjectRequestStatus::IN_PROGRESS) {
            return 6 + $offset;
        }

        return match ($request->status) {
            ProjectRequestStatus::SCHEDULED => 5 + $offset,
            ProjectRequestStatus::APPROVED => 4 + $offset,
            ProjectRequestStatus::PENDING_MANAGER => 3 + $offset,
            ProjectRequestStatus::PENDING_SPV => 2 + $offset,
            ProjectRequestStatus::PENDING_DEPARTMENT => 1,
            ProjectRequestStatus::NEEDS_INFO => $request->needs_info_stage === 'department' ? 1 : 2 + $offset,
            ProjectRequestStatus::PENDING_MEETING => 1 + $offset,
            default => 0,
        };
    }

    private function featureStages(FeatureRequest $request, Collection $tasks, Collection $checkpoints, Collection $logs, ?TaskBreakdown $breakdown): array
    {
        $stages = [
            ['key' => 'submitted', 'label' => 'Submitted', 'at' => $request->created_at],
            ['key' => 'review', 'label' => 'Supervisor review', 'at' => $this->actionTime($logs, ['feature_request.pending_review'])],
            ['key' => 'scheduling', 'label' => 'Scheduling', 'at' => $this->actionTime($logs, ['feature_request.approved'])],
            ['key' => 'planning', 'label' => 'Work planning', 'at' => $this->actionTime($logs, ['feature_request.scheduled'])],
            ['key' => 'delivery', 'label' => 'Delivery', 'at' => $breakdown?->accepted_at ?? $tasks->min('created_at')],
            ['key' => 'validation', 'label' => 'Validation', 'at' => $checkpoints->min('opened_at')],
            ['key' => 'delivered', 'label' => 'Delivered', 'at' => $this->actionTime($logs, ['feature_request.delivered']) ?? $tasks->max('completed_at')],
        ];

        if ($this->requiresDepartmentApproval($request)) {
            array_splice($stages, 1, 0, [[
                'key' => 'department',
                'label' => 'Department approval',
                'at' => $request->department_reviewed_at,
            ]]);
        }

        return $stages;
    }

    private function projectStages(ProjectRequest $request, Collection $tasks, Collection $checkpoints, Collection $logs, ?TaskBreakdown $breakdown): array
    {
        $stages = [
            ['key' => 'submitted', 'label' => 'Submitted', 'at' => $request->created_at],
            ['key' => 'scoping', 'label' => 'Scoping meeting', 'at' => $this->actionTime($logs, ['project_request.pending_meeting'])],
            ['key' => 'supervisor', 'label' => 'Supervisor approval', 'at' => $request->meeting_held_at],
            ['key' => 'manager', 'label' => 'Manager approval', 'at' => $request->spv_at],
            ['key' => 'scheduling', 'label' => 'Scheduling', 'at' => $request->manager_at],
            ['key' => 'planning', 'label' => 'Work planning', 'at' => $this->actionTime($logs, ['project_request.scheduled'])],
            ['key' => 'delivery', 'label' => 'Delivery', 'at' => $breakdown?->accepted_at ?? $tasks->min('created_at')],
            ['key' => 'validation', 'label' => 'Validation', 'at' => $checkpoints->min('opened_at')],
            ['key' => 'delivered', 'label' => 'Delivered', 'at' => $this->actionTime($logs, ['project_request.delivered']) ?? $tasks->max('completed_at')],
        ];

        if ($this->requiresDepartmentApproval($request)) {
            array_splice($stages, 1, 0, [[
                'key' => 'department',
                'label' => 'Department approval',
                'at' => $request->department_reviewed_at,
            ]]);
        }

        return $stages;
    }

    private function description(FeatureRequest|ProjectRequest $request, Collection $tasks, Collection $open, ?TaskBreakdown $breakdown, int $blocked): string
    {
        if ($request->status->value === 'rejected') {
            return 'The request stopped during approval. The decision reason is shown below the timeline.';
        }

        if ($request->status->value === 'taken_down') {
            return 'The request was stopped after its validation deadline expired.';
        }

        if ($request->status->value === 'needs_info') {
            return 'The reviewer is waiting for more information from you.';
        }

        if ($open->isNotEmpty()) {
            return $open->count().' work result(s) are waiting for your validation. Respond before the deadline.';
        }

        if ($tasks->isNotEmpty()) {
            $completed = $tasks->whereNotNull('completed_at')->count();

            return $completed.' of '.$tasks->count().' tasks completed'.($blocked ? '; '.$blocked.' task(s) are still waiting on prerequisites.' : '.');
        }

        if ($breakdown?->status === BreakdownStatus::PENDING) {
            return 'AI is preparing a work plan for ITD review.';
        }

        if ($breakdown?->status === BreakdownStatus::READY) {
            return 'The work plan is ready and being reviewed before it is added to the board.';
        }

        return match ($request->status->value) {
            'pending_department' => 'The request is waiting for your department manager or coordinator.',
            'pending_review' => 'The request is waiting for a supervisor decision.',
            'pending_meeting' => $request instanceof ProjectRequest && $request->meeting
                ? 'The scoping meeting is scheduled and waiting to take place.'
                : 'ITD will schedule the scoping meeting.',
            'pending_spv' => 'Scoping is complete and the proposal is waiting for supervisor approval.',
            'pending_manager' => 'The supervisor approved it; the proposal is now waiting for manager approval.',
            'approved' => 'The request is approved and waiting for an available capacity slot.',
            'scheduled' => 'The assignee and schedule are set; ITD is preparing the work plan.',
            'delivered' => 'All work and validations are complete.',
            default => 'The request is recorded and will move to the next stage.',
        };
    }

    private function currentLabel(FeatureRequest|ProjectRequest $request, string $fallback): string
    {
        return match ($request->status->value) {
            'rejected' => 'Not approved',
            'taken_down' => 'Stopped',
            'delivered' => 'Delivered',
            'needs_info' => 'Waiting for your information',
            default => $fallback,
        };
    }

    private function scheduleLabel(FeatureRequest|ProjectRequest $request, string $timezone): ?string
    {
        $start = $request->scheduled_start?->setTimezone($timezone);
        $due = $request->scheduled_due?->setTimezone($timezone);

        if (! $start && ! $due) {
            return null;
        }

        if (! $start || ! $due || $start->isSameDay($due)) {
            return ($start ?? $due)->format('j M Y');
        }

        return $start->format('j M').' – '.$due->format('j M Y');
    }

    private function actionTime(Collection $logs, array $actions): ?CarbonInterface
    {
        return $logs->first(fn (ActivityLog $log) => in_array($log->action, $actions, true))?->created_at;
    }

    private function requiresDepartmentApproval(FeatureRequest|ProjectRequest $request): bool
    {
        return $request->requester_department_external_id !== null
            && strcasecmp((string) $request->requester_department_code, config('organization.it_department_code')) !== 0
            && OrganizationRankGroup::fromCode($request->requester_job_rank_code) !== OrganizationRankGroup::MANAGEMENT;
    }
}
