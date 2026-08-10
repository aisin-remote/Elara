<?php

namespace App\Actions\Task;

use App\Actions\Validation\OpenValidationCheckpoints;
use App\Enums\TaskStatusCategory;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskMoveOperation;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use App\Services\TaskPositionService;
use Illuminate\Support\Facades\DB;

class MoveTask
{
    public function __construct(
        private readonly TaskPositionService $positions,
        private readonly NotificationPreferenceService $notifications,
        private readonly OpenValidationCheckpoints $checkpoints,
    ) {}

    public function handle(Task $task, User $actor, array $data, ?string $ipAddress = null): ?Task
    {
        $previousStatusId = $task->status_id;
        $movedTask = DB::transaction(function () use ($task, $actor, $data, $ipAddress) {
            if (TaskMoveOperation::query()->where('operation_id', $data['operation_id'])->exists()) {
                return $task->fresh(['status']);
            }

            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($lockedTask->version !== (int) $data['version']) {
                return null;
            }

            $status = TaskStatus::query()
                ->active()
                ->where('project_id', $task->project_id)
                ->where('public_id', $data['status_public_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $position = $this->positions->positionFor(
                $lockedTask,
                $status,
                $data['before_task_public_id'] ?? null,
                $data['after_task_public_id'] ?? null,
            );

            $lockedTask->update([
                'status_id' => $status->id,
                'status_changed_at' => $lockedTask->status_id === $status->id ? $lockedTask->status_changed_at : now(),
                'position' => $position,
                'completed_at' => $status->category === TaskStatusCategory::COMPLETED
                    ? ($lockedTask->completed_at ?? now())
                    : null,
                'version' => $lockedTask->version + 1,
            ]);
            TaskMoveOperation::create([
                'operation_id' => $data['operation_id'],
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'created_at' => now(),
            ]);
            ActivityLog::record($task->workspace, $lockedTask, 'task.moved', $actor, ['status' => $status->name], $ipAddress);

            return $lockedTask->load('status');
        });

        if ($movedTask) {
            $this->checkpoints->handle($movedTask);
        }

        if ($movedTask && $previousStatusId !== $movedTask->status_id) {
            $movedTask->loadMissing(['assignees', 'watchers', 'workspace']);
            $movedTask->assignees->merge($movedTask->watchers)->unique('id')->where('id', '!=', $actor->id)->each(fn (User $recipient) => $this->notifications->notify(
                $recipient,
                $movedTask->workspace,
                'task_status_changed',
                'Task status changed',
                $actor->name.' moved “'.$movedTask->title.'” to '.$movedTask->status->name.'.',
                route('app.tasks.show', $movedTask),
                ['task_public_id' => $movedTask->public_id, 'status' => $movedTask->status->name],
            ));
        }

        return $movedTask;
    }
}
