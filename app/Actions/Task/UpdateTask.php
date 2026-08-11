<?php

namespace App\Actions\Task;

use App\Actions\Validation\OpenValidationCheckpoints;
use App\Enums\TaskStatusCategory;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use App\Services\Planning\DateShiftService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTask
{
    public function __construct(
        private readonly CreateTask $createTask,
        private readonly NotificationPreferenceService $notifications,
        private readonly OpenValidationCheckpoints $checkpoints,
        private readonly DateShiftService $dateShift,
    ) {}

    public function handle(Task $task, User $actor, array $data, int $version, ?string $ipAddress = null): ?Task
    {
        $previousStatusId = $task->status_id;
        $previousAssigneeIds = $task->assignees()->pluck('users.id');
        $scheduleTouched = array_key_exists('start_at', $data)
            || array_key_exists('due_at', $data)
            || array_key_exists('estimate_minutes', $data);

        $task = DB::transaction(function () use ($task, $actor, $data, $version, $ipAddress) {
            $status = TaskStatus::query()
                ->active()
                ->where('project_id', $task->project_id)
                ->where('public_id', $data['status_public_id'])
                ->first();
            if (! $status) {
                throw ValidationException::withMessages(['status_public_id' => 'Choose a status from this project.']);
            }
            $categoryId = $this->categoryId($task, $data['category_public_id'] ?? null);
            $featureId = array_key_exists('feature_public_id', $data)
                ? $this->createTask->featureId($task->project, $data['feature_public_id'])
                : $task->feature_id;
            $milestoneId = $this->createTask->milestoneId($task->project, $data['milestone_public_id'] ?? null);
            $attributes = [
                ...Arr::except($data, ['status_public_id', 'category_public_id', 'feature_public_id', 'milestone_public_id', 'assignee_public_ids', 'attachments']),
                'status_id' => $status->id,
                'status_changed_at' => $task->status_id === $status->id ? $task->status_changed_at : now(),
                'category_id' => $categoryId,
                'feature_id' => $featureId,
                'milestone_id' => $milestoneId,
                'completed_at' => $status->category === TaskStatusCategory::COMPLETED
                    ? ($task->completed_at ?? now())
                    : null,
                'version' => DB::raw('version + 1'),
            ];

            $updated = Task::query()->whereKey($task->id)->where('version', $version)->update($attributes);

            if (! $updated) {
                return null;
            }

            $task = $task->fresh();
            $task->assignees()->sync($this->createTask->assigneePayload($task->project, $actor, $data['assignee_public_ids'] ?? []));
            ActivityLog::record($task->workspace, $task, 'task.updated', $actor, ipAddress: $ipAddress);

            return $task->load(['status', 'category', 'milestone', 'assignees', 'dependencies']);
        });

        if (! $task) {
            return null;
        }

        $this->checkpoints->handle($task);

        if ($scheduleTouched && $task->completed_at === null) {
            $this->dateShift->shiftFrom($task->fresh(['assignees', 'dependencies', 'dependents']), $actor, $ipAddress);
            $task = $task->fresh(['status', 'category', 'milestone', 'assignees', 'dependencies']);
        }

        $task->assignees->whereNotIn('id', $previousAssigneeIds)->where('id', '!=', $actor->id)->each(fn (User $assignee) => $this->notifications->notify(
            $assignee,
            $task->workspace,
            'task_assigned',
            'Task assigned to you',
            $actor->name.' assigned “'.$task->title.'” to you.',
            route('app.tasks.show', $task),
            ['task_public_id' => $task->public_id],
        ));

        if ($previousStatusId !== $task->status_id) {
            $task->loadMissing(['watchers', 'workspace']);
            $task->assignees->merge($task->watchers)->unique('id')->where('id', '!=', $actor->id)->each(fn (User $recipient) => $this->notifications->notify(
                $recipient,
                $task->workspace,
                'task_status_changed',
                'Task status changed',
                $actor->name.' moved “'.$task->title.'” to '.$task->status->name.'.',
                route('app.tasks.show', $task),
                ['task_public_id' => $task->public_id, 'status' => $task->status->name],
            ));
        }

        return $task;
    }

    private function categoryId(Task $task, ?string $publicId): ?int
    {
        if (! $publicId) {
            return null;
        }

        return TaskCategory::query()
            ->where('workspace_id', $task->workspace_id)
            ->where('public_id', $publicId)
            ->value('id') ?? throw ValidationException::withMessages(['category_public_id' => 'Choose a category from this workspace.']);
    }
}
