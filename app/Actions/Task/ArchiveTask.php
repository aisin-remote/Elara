<?php

namespace App\Actions\Task;

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArchiveTask
{
    public function archive(Task $task, User $actor, ?string $ipAddress = null): void
    {
        DB::transaction(function () use ($task, $actor, $ipAddress): void {
            $task->update(['archived_at' => now(), 'version' => $task->version + 1]);
            ActivityLog::record($task->workspace, $task, 'task.archived', $actor, ipAddress: $ipAddress);
            $task->delete();
        });
    }

    public function restore(Task $task, User $actor, ?string $ipAddress = null): Task
    {
        return DB::transaction(function () use ($task, $actor, $ipAddress) {
            $task->restore();
            $task->update(['archived_at' => null, 'version' => $task->version + 1]);
            ActivityLog::record($task->workspace, $task, 'task.restored', $actor, ipAddress: $ipAddress);

            return $task->load('status');
        });
    }
}
