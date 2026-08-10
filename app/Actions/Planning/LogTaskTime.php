<?php

namespace App\Actions\Planning;

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LogTaskTime
{
    /**
     * @param  array{minutes: int, worked_on: string, note?: ?string}  $data
     */
    public function handle(Task $task, User $actor, array $data, ?string $ipAddress = null): TaskTimeEntry
    {
        return DB::transaction(function () use ($task, $actor, $data, $ipAddress): TaskTimeEntry {
            $entry = TaskTimeEntry::query()->create([
                'workspace_id' => $task->workspace_id,
                'task_id' => $task->id,
                'user_id' => $actor->id,
                'minutes' => $data['minutes'],
                'worked_on' => $data['worked_on'],
                'note' => $data['note'] ?? null,
            ]);

            ActivityLog::record(
                $task->workspace,
                $task,
                'task.time_logged',
                $actor,
                ['minutes' => $entry->minutes, 'worked_on' => $entry->worked_on->toDateString()],
                $ipAddress,
            );

            return $entry;
        });
    }
}
