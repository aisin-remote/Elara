<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Validation\ValidationException;

class TaskPositionService
{
    public function next(TaskStatus $status): int
    {
        return ((int) $status->tasks()->max('position')) + 1024;
    }

    public function positionFor(Task $task, TaskStatus $status, ?string $beforePublicId, ?string $afterPublicId): int
    {
        $before = $this->neighbor($task, $status, $beforePublicId);
        $after = $this->neighbor($task, $status, $afterPublicId);

        if ($before && $after && $before->position >= $after->position) {
            throw ValidationException::withMessages(['position' => 'The requested task order is invalid.']);
        }

        if ($before && $after && $after->position - $before->position <= 1) {
            $this->reindex($task, $status);
            $before->refresh();
            $after->refresh();
        }

        return match (true) {
            $before && $after => (int) floor(($before->position + $after->position) / 2),
            (bool) $before => $before->position + 1024,
            (bool) $after => max(1, (int) floor($after->position / 2)),
            default => 1024,
        };
    }

    private function neighbor(Task $task, TaskStatus $status, ?string $publicId): ?Task
    {
        if (! $publicId) {
            return null;
        }

        return Task::query()
            ->where('project_id', $task->project_id)
            ->where('status_id', $status->id)
            ->where('public_id', $publicId)
            ->whereKeyNot($task->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function reindex(Task $task, TaskStatus $status): void
    {
        $status->tasks()
            ->whereKeyNot($task->id)
            ->orderBy('position')
            ->lockForUpdate()
            ->get()
            ->each(fn (Task $item, int $index) => $item->updateQuietly(['position' => ($index + 1) * 1024]));
    }
}
