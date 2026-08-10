<?php

namespace App\Services\Planning;

use App\Enums\DependencyType;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CapacityPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Classic CPM over Orbitra tasks: early/late dates and total slack in working days.
 * Duration comes from estimate_minutes against the assignee's daily capacity (workspace default when unassigned).
 */
class CriticalPathAnalyzer
{
    public function __construct(
        private readonly CapacityPlanner $planner,
    ) {}

    /**
     * @return array{
     *     tasks: array<int, array{task_id: int, public_id: string, title: string, duration_days: int, early_start: int, early_finish: int, late_start: int, late_finish: int, slack: int, is_critical: bool}>,
     *     critical_public_ids: array<int, string>,
     *     project_duration_days: int,
     *     projected_finish: ?CarbonImmutable
     * }
     */
    public function forProject(Project $project): array
    {
        $workspace = $project->workspace;
        $tasks = $project->tasks()
            ->whereNull('archived_at')
            ->with(['assignees', 'dependencies' => fn ($q) => $q->whereNull('archived_at')])
            ->get()
            ->keyBy('id');

        return $this->analyze($workspace, $tasks);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array{
     *     tasks: array<int, array{task_id: int, public_id: string, title: string, duration_days: int, early_start: int, early_finish: int, late_start: int, late_finish: int, slack: int, is_critical: bool}>,
     *     critical_public_ids: array<int, string>,
     *     project_duration_days: int,
     *     projected_finish: ?CarbonImmutable
     * }
     */
    public function analyze(Workspace $workspace, Collection $tasks): array
    {
        if ($tasks->isEmpty()) {
            return [
                'tasks' => [],
                'critical_public_ids' => [],
                'project_duration_days' => 0,
                'projected_finish' => null,
            ];
        }

        $durations = [];
        foreach ($tasks as $task) {
            $durations[$task->id] = $this->durationDays($workspace, $task);
        }

        $preds = [];
        $succs = [];
        foreach ($tasks as $task) {
            $preds[$task->id] = [];
            $succs[$task->id] = [];
        }

        foreach ($tasks as $task) {
            foreach ($task->dependencies as $dependency) {
                if (! $tasks->has($dependency->id)) {
                    continue;
                }
                $preds[$task->id][] = $dependency->id;
                $succs[$dependency->id][] = $task->id;
            }
        }

        $order = $this->topoSort(array_keys($durations), $preds);
        $earlyStart = [];
        $earlyFinish = [];

        foreach ($order as $id) {
            $es = 0;
            foreach ($preds[$id] as $predId) {
                $type = $this->edgeType($tasks[$id], $predId);
                $lagDays = $this->lagDays($tasks[$id], $predId);
                $constraint = match ($type) {
                    DependencyType::START_TO_START => $earlyStart[$predId] + $lagDays,
                    DependencyType::FINISH_TO_FINISH => max(0, $earlyFinish[$predId] + $lagDays - $durations[$id]),
                    DependencyType::START_TO_FINISH => max(0, $earlyStart[$predId] + $lagDays - $durations[$id]),
                    default => $earlyFinish[$predId] + $lagDays,
                };
                $es = max($es, $constraint);
            }
            $earlyStart[$id] = $es;
            $earlyFinish[$id] = $es + $durations[$id];
        }

        $projectDuration = max($earlyFinish ?: [0]);
        $lateFinish = [];
        $lateStart = [];

        foreach (array_reverse($order) as $id) {
            $lf = $projectDuration;
            if ($succs[$id] !== []) {
                $lf = min(array_map(function (int $succId) use ($tasks, $id, $lateStart, $lateFinish, $durations): int {
                    $type = $this->edgeType($tasks[$succId], $id);
                    $lagDays = $this->lagDays($tasks[$succId], $id);

                    return match ($type) {
                        DependencyType::START_TO_START => $lateStart[$succId] - $lagDays + $durations[$id],
                        DependencyType::FINISH_TO_FINISH => $lateFinish[$succId] - $lagDays,
                        DependencyType::START_TO_FINISH => $lateFinish[$succId] - $lagDays + $durations[$id],
                        default => $lateStart[$succId] - $lagDays,
                    };
                }, $succs[$id]));
            }
            $lateFinish[$id] = $lf;
            $lateStart[$id] = $lf - $durations[$id];
        }

        $timezone = $workspace->timezone ?: config('app.timezone');
        $origin = CarbonImmutable::now($timezone)->startOfDay();
        $rows = [];
        $critical = [];

        foreach ($order as $id) {
            $slack = max(0, $lateStart[$id] - $earlyStart[$id]);
            $isCritical = $slack === 0 && $durations[$id] > 0;
            $task = $tasks[$id];
            $rows[] = [
                'task_id' => $id,
                'public_id' => $task->public_id,
                'title' => $task->title,
                'duration_days' => $durations[$id],
                'early_start' => $earlyStart[$id],
                'early_finish' => $earlyFinish[$id],
                'late_start' => $lateStart[$id],
                'late_finish' => $lateFinish[$id],
                'slack' => $slack,
                'is_critical' => $isCritical,
            ];
            if ($isCritical) {
                $critical[] = $task->public_id;
            }
        }

        return [
            'tasks' => $rows,
            'critical_public_ids' => $critical,
            'project_duration_days' => $projectDuration,
            'projected_finish' => $projectDuration > 0
                ? $this->addWorkingDays($workspace, $origin, $projectDuration)
                : $origin,
        ];
    }

    private function durationDays(Workspace $workspace, Task $task): int
    {
        if ($task->completed_at !== null) {
            return 0;
        }

        $minutes = max(0, (int) ($task->estimate_minutes ?? 0));
        if ($minutes === 0) {
            return 1;
        }

        $assignee = $task->assignees->first();
        $daily = $assignee instanceof User
            ? max(1, (int) round($this->planner->hoursPerDay($workspace, $assignee) * 60))
            : max(1, (int) round(6 * 60));

        return max(1, (int) ceil($minutes / $daily));
    }

    private function edgeType(Task $dependent, int $prerequisiteId): DependencyType
    {
        $edge = $dependent->dependencies->firstWhere('id', $prerequisiteId);

        return DependencyType::tryFrom((string) ($edge?->pivot?->type ?? DependencyType::FINISH_TO_START->value))
            ?? DependencyType::FINISH_TO_START;
    }

    private function lagDays(Task $dependent, int $prerequisiteId): int
    {
        $edge = $dependent->dependencies->firstWhere('id', $prerequisiteId);
        $lagMinutes = (int) ($edge?->pivot?->lag_minutes ?? 0);

        return (int) ceil(max(0, $lagMinutes) / (6 * 60));
    }

    /**
     * @param  array<int, int>  $ids
     * @param  array<int, array<int, int>>  $preds
     * @return array<int, int>
     */
    private function topoSort(array $ids, array $preds): array
    {
        $remaining = array_fill_keys($ids, true);
        $order = [];

        while ($remaining !== []) {
            $ready = [];
            foreach (array_keys($remaining) as $id) {
                $blocked = false;
                foreach ($preds[$id] as $predId) {
                    if (isset($remaining[$predId])) {
                        $blocked = true;
                        break;
                    }
                }
                if (! $blocked) {
                    $ready[] = $id;
                }
            }

            if ($ready === []) {
                // Cycle remnant — append remaining in id order so callers still get a result.
                $ready = array_keys($remaining);
            }

            sort($ready);
            foreach ($ready as $id) {
                $order[] = $id;
                unset($remaining[$id]);
            }
        }

        return $order;
    }

    private function addWorkingDays(Workspace $workspace, CarbonImmutable $from, int $days): CarbonImmutable
    {
        $cursor = $from;
        $added = 0;

        while ($added < $days) {
            $cursor = $cursor->addDay();
            if ($cursor->isWeekend()) {
                continue;
            }
            $added++;
        }

        return $cursor;
    }
}
