<?php

namespace App\Actions\Planning;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CaptureProjectBaseline
{
    public function handle(Project $project, User $actor, ?string $ipAddress = null): int
    {
        return DB::transaction(function () use ($project, $actor, $ipAddress): int {
            $count = 0;

            $project->tasks()
                ->whereNull('archived_at')
                ->where(fn ($query) => $query->whereNotNull('start_at')->orWhereNotNull('due_at'))
                ->orderBy('id')
                ->each(function ($task) use (&$count): void {
                    $task->forceFill([
                        'baseline_start_at' => $task->start_at,
                        'baseline_due_at' => $task->due_at,
                    ])->save();
                    $count++;
                });

            ActivityLog::record(
                $project->workspace,
                $project,
                'project.baseline_captured',
                $actor,
                ['tasks' => $count],
                $ipAddress,
            );

            return $count;
        });
    }
}
