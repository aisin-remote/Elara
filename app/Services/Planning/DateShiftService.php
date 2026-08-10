<?php

namespace App\Services\Planning;

use App\Enums\DependencyType;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CapacityPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pushes unfinished task windows later so dependency constraints still hold.
 * Never pulls dates earlier — that is a human planning choice.
 */
class DateShiftService
{
    public function __construct(private readonly CapacityPlanner $planner) {}

    /**
     * @return array{shifted: int, tasks: array<int, array{public_id: string, start_at: string, due_at: string}>}
     */
    public function shiftProject(Project $project, ?User $actor = null, ?string $ipAddress = null): array
    {
        $tasks = $project->tasks()
            ->whereNull('archived_at')
            ->whereNull('completed_at')
            ->with(['assignees', 'dependencies'])
            ->get()
            ->keyBy('id');

        return $this->shiftCollection($project->workspace, $tasks, $actor, $ipAddress);
    }

    /**
     * Shift a task and every unfinished dependent reachable from it (same workspace).
     *
     * @return array{shifted: int, tasks: array<int, array{public_id: string, start_at: string, due_at: string}>}
     */
    public function shiftFrom(Task $seed, ?User $actor = null, ?string $ipAddress = null): array
    {
        $workspace = $seed->workspace;
        $all = Task::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->with(['assignees', 'dependencies', 'dependents'])
            ->get()
            ->keyBy('id');

        $reachable = [$seed->id => true];
        $frontier = [$seed->id];

        while ($frontier !== []) {
            $next = [];
            foreach ($frontier as $id) {
                if (! isset($all[$id])) {
                    continue;
                }

                foreach ($all[$id]->dependents as $dependent) {
                    if (! isset($all[$dependent->id]) || isset($reachable[$dependent->id])) {
                        continue;
                    }
                    if ($all[$dependent->id]->completed_at !== null) {
                        continue;
                    }
                    $reachable[$dependent->id] = true;
                    $next[] = $dependent->id;
                }
            }
            $frontier = $next;
        }

        $subset = $all->only(array_keys($reachable))
            ->filter(fn (Task $task) => $task->completed_at === null);

        return $this->shiftCollection($workspace, $subset, $actor, $ipAddress);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array{shifted: int, tasks: array<int, array{public_id: string, start_at: string, due_at: string}>}
     */
    private function shiftCollection(
        Workspace $workspace,
        Collection $tasks,
        ?User $actor,
        ?string $ipAddress,
    ): array {
        if ($tasks->isEmpty()) {
            return ['shifted' => 0, 'tasks' => []];
        }

        $prereqIds = $tasks->flatMap(fn (Task $task) => $task->dependencies->pluck('id'))->unique();
        $graph = Task::query()
            ->whereIn('id', $tasks->keys()->merge($prereqIds)->unique())
            ->with(['assignees', 'dependencies'])
            ->get()
            ->keyBy('id');

        // Prefer freshly loaded dependency pivots on the working set.
        foreach ($tasks as $id => $task) {
            if ($graph->has($id)) {
                $tasks[$id] = $graph[$id];
            }
        }

        $order = $this->topoOrder($tasks);
        $shifted = [];

        DB::transaction(function () use ($workspace, $tasks, $graph, $order, $actor, $ipAddress, &$shifted): void {
            $resolved = [];

            foreach ($order as $id) {
                $task = $tasks[$id];
                $notBefore = $this->earliestStart($workspace, $task, $graph, $resolved);
                $minutes = max(1, (int) ($task->estimate_minutes ?: 480));
                $assignee = $task->assignees->first();

                if ($assignee instanceof User) {
                    $window = $this->planner->windowFrom($workspace, $assignee, $minutes, $notBefore);
                    $start = $window['start'] ?? $notBefore;
                    $due = isset($window['due'])
                        ? $window['due']->setTime(17, 0)
                        : $notBefore->addDay()->setTime(17, 0);
                } else {
                    $start = $notBefore;
                    $due = $notBefore->addDays(max(1, (int) ceil($minutes / 360)))->setTime(17, 0);
                }

                $currentStart = $task->start_at
                    ? CarbonImmutable::parse($task->start_at)->setTimezone($workspace->timezone ?: config('app.timezone'))->startOfDay()
                    : null;
                $currentDue = $task->due_at
                    ? CarbonImmutable::parse($task->due_at)->setTimezone($workspace->timezone ?: config('app.timezone'))
                    : null;

                if ($currentStart !== null && $currentStart->gt($start)) {
                    $start = $currentStart;
                }
                if ($currentDue !== null && $currentDue->gt($due)) {
                    $due = $currentDue;
                }

                $unchanged = $currentDue !== null
                    && $due->equalTo($currentDue)
                    && ($currentStart === null || $start->equalTo($currentStart));

                if ($unchanged) {
                    $resolved[$id] = ['start' => $start, 'due' => $due];

                    continue;
                }

                $task->forceFill([
                    'start_at' => $start->setTime(9, 0),
                    'due_at' => $due,
                ])->save();

                $resolved[$id] = ['start' => $start, 'due' => $due];
                $shifted[] = [
                    'public_id' => $task->public_id,
                    'start_at' => $start->toIso8601String(),
                    'due_at' => $due->toIso8601String(),
                ];

                if ($actor) {
                    ActivityLog::record(
                        $workspace,
                        $task,
                        'task.dates_shifted',
                        $actor,
                        ['start_at' => $start->toDateString(), 'due_at' => $due->toDateString()],
                        $ipAddress,
                    );
                }
            }
        });

        return ['shifted' => count($shifted), 'tasks' => $shifted];
    }

    /**
     * @param  Collection<int, Task>  $graph
     * @param  array<int, array{start: CarbonImmutable, due: CarbonImmutable}>  $resolved
     */
    private function earliestStart(Workspace $workspace, Task $task, Collection $graph, array $resolved): CarbonImmutable
    {
        $timezone = $workspace->timezone ?: config('app.timezone');
        $earliest = CarbonImmutable::now($timezone)->startOfDay();

        foreach ($task->dependencies as $dependency) {
            $type = DependencyType::tryFrom((string) ($dependency->pivot->type ?? 'fs')) ?? DependencyType::FINISH_TO_START;
            $lagMinutes = (int) ($dependency->pivot->lag_minutes ?? 0);
            $lagDays = (int) ceil(max(0, $lagMinutes) / (6 * 60));

            $pred = $resolved[$dependency->id] ?? null;
            if ($pred === null && $graph->has($dependency->id)) {
                $node = $graph[$dependency->id];
                $predStart = $node->start_at
                    ? CarbonImmutable::parse($node->start_at)->setTimezone($timezone)->startOfDay()
                    : $earliest;
                $predDue = $node->due_at
                    ? CarbonImmutable::parse($node->due_at)->setTimezone($timezone)->startOfDay()
                    : $predStart;
                if ($node->completed_at) {
                    $predDue = CarbonImmutable::parse($node->completed_at)->setTimezone($timezone)->startOfDay();
                }
                $pred = ['start' => $predStart, 'due' => $predDue];
            }

            if ($pred === null) {
                continue;
            }

            $constraint = match ($type) {
                DependencyType::START_TO_START => $pred['start']->addDays($lagDays),
                DependencyType::FINISH_TO_FINISH, DependencyType::START_TO_FINISH => $pred['start']->addDays($lagDays),
                default => $lagDays > 0 ? $pred['due']->addDays($lagDays) : $pred['due']->addDay(),
            };

            $earliest = $earliest->max($constraint->startOfDay());
        }

        return $earliest;
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array<int, int>
     */
    private function topoOrder(Collection $tasks): array
    {
        $ids = $tasks->keys()->all();
        $remaining = array_fill_keys($ids, true);
        $order = [];

        while ($remaining !== []) {
            $ready = [];
            foreach (array_keys($remaining) as $id) {
                $blocked = false;
                foreach ($tasks[$id]->dependencies as $dependency) {
                    if (isset($remaining[$dependency->id])) {
                        $blocked = true;
                        break;
                    }
                }
                if (! $blocked) {
                    $ready[] = $id;
                }
            }

            if ($ready === []) {
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
}
