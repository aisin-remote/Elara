<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatusCategory;
use App\Models\Feature;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\Task;
use App\Models\Workspace;
use App\Support\GanttTimeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RequesterItTimeline
{
    private const TASK_LIMIT = 200;

    /** @return array<string, mixed> */
    public function build(Workspace $workspace, ?int $departmentId, ?string $scale, ?string $view = null): array
    {
        $view = $view === 'features' ? 'features' : 'projects';
        $timeline = new GanttTimeline($scale, $workspace->timezone);

        $taskQuery = Task::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->whereHas('status', fn (Builder $status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value));

        if ($view === 'features') {
            $taskQuery
                ->whereNotNull('feature_id')
                ->whereHas('feature', fn (Builder $feature) => $feature
                    ->active()
                    ->whereHas('project', fn (Builder $project) => $project->where('status', '!=', ProjectStatus::ARCHIVED->value)));
        } else {
            $taskQuery->whereHas('project', fn (Builder $project) => $project
                ->delivery()
                ->where('status', '!=', ProjectStatus::ARCHIVED->value));
        }

        if ($departmentId !== null) {
            $taskQuery->whereIn(
                $view === 'features' ? 'tasks.feature_id' : 'tasks.project_id',
                $view === 'features'
                    ? $this->departmentFeatureIds($workspace, $departmentId)
                    : $this->departmentProjectIds($workspace, $departmentId),
            );
        }

        $taskQuery
            ->whereRaw('COALESCE(tasks.due_at, tasks.start_at) >= ?', [$timeline->from->utc()])
            ->whereRaw('COALESCE(tasks.start_at, tasks.due_at) <= ?', [$timeline->to->utc()]);
        $scheduledTaskCount = (clone $taskQuery)->count();
        $tasks = $taskQuery
            ->with([
                'project:id,public_id,name,color',
                'status:id,name,color,category',
            ])
            ->withCount([
                'checklistItems as checklist_total',
                'checklistItems as checklist_completed' => fn (Builder $items) => $items->where('is_completed', true),
            ])
            ->orderByRaw('COALESCE(tasks.start_at, tasks.due_at)')
            // ponytail: a date window plus 200 rows keeps one overview useful; paginate only if real IT volume exceeds it.
            ->limit(self::TASK_LIMIT)
            ->get();

        $taskRows = $tasks->map(function (Task $task) use ($timeline, $view, $workspace) {
            $start = CarbonImmutable::parse($task->start_at ?? $task->due_at)->setTimezone($workspace->timezone)->startOfDay();
            $end = CarbonImmutable::parse($task->due_at ?? $task->start_at)->setTimezone($workspace->timezone)->endOfDay();
            $bar = $timeline->bar($start->min($end), $end->max($start));

            if ($bar === null) {
                return null;
            }

            return [
                'group_id' => $view === 'features' ? $task->feature_id : $task->project_id,
                'title' => $task->title,
                'status' => $task->status->name,
                'color' => $task->status->color ?: $task->project->color,
                'date_label' => $start->isSameDay($end) ? $start->format('M j') : $start->format('M j').' – '.$end->format('M j'),
                'progress' => $task->checklist_total
                    ? (int) round($task->checklist_completed / $task->checklist_total * 100)
                    : ($task->completed_at ? 100 : 0),
                'is_overdue' => $task->completed_at === null && $task->due_at?->isPast(),
                ...$bar,
            ];
        })->filter()->values();

        [$timelineRows, $itemCount] = $view === 'features'
            ? $this->featureRows($workspace, $departmentId, $timeline, $taskRows)
            : $this->projectRows($workspace, $departmentId, $timeline, $taskRows);

        return [
            'view' => $view,
            'timeline' => $timeline,
            'timelineRows' => $timelineRows,
            // Kept for the public department homepage, which intentionally remains project-only.
            'projectRows' => $view === 'projects' ? $timelineRows : collect(),
            'itemCount' => $itemCount,
            'projectCount' => $view === 'projects' ? $itemCount : 0,
            'scheduledTaskCount' => $scheduledTaskCount,
            'hiddenTaskCount' => max(0, $scheduledTaskCount - $taskRows->count()),
            'updatedAt' => now($workspace->timezone),
        ];
    }

    /** @return array{0: Collection<int, array<string, mixed>>, 1: int} */
    private function projectRows(Workspace $workspace, ?int $departmentId, GanttTimeline $timeline, Collection $taskRows): array
    {
        $query = Project::query()
            ->delivery()
            ->where('workspace_id', $workspace->id)
            ->where('status', '!=', ProjectStatus::ARCHIVED->value);

        if ($departmentId !== null) {
            $query->whereIn('projects.id', $this->departmentProjectIds($workspace, $departmentId));
        }

        $count = (clone $query)->count();
        $rows = $query
            ->addSelect([
                'timeline_first_task_at' => Task::query()
                    ->selectRaw('MIN(COALESCE(tasks.start_at, tasks.due_at))')
                    ->whereColumn('tasks.project_id', 'projects.id')
                    ->whereNull('tasks.archived_at'),
                'timeline_last_task_at' => Task::query()
                    ->selectRaw('MAX(COALESCE(tasks.due_at, tasks.start_at))')
                    ->whereColumn('tasks.project_id', 'projects.id')
                    ->whereNull('tasks.archived_at'),
            ])
            ->withCount([
                'tasks as timeline_total_tasks_count' => fn (Builder $tasks) => $tasks
                    ->whereNull('archived_at')
                    ->whereHas('status', fn (Builder $status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value)),
                'tasks as timeline_completed_tasks_count' => fn (Builder $tasks) => $tasks
                    ->whereNull('archived_at')
                    ->whereNotNull('completed_at')
                    ->whereHas('status', fn (Builder $status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value)),
            ])
            ->orderByRaw('COALESCE(projects.start_date, timeline_first_task_at)')
            ->limit(60)
            ->get()
            ->map(function (Project $project) use ($taskRows, $timeline, $workspace) {
                $startValue = $project->start_date?->toDateString() ?? $project->timeline_first_task_at;
                $endValue = $project->due_date?->toDateString() ?? $project->timeline_last_task_at;

                if (! $startValue && ! $endValue) {
                    return null;
                }

                $start = CarbonImmutable::parse($startValue ?? $endValue, $workspace->timezone)->setTimezone($workspace->timezone)->startOfDay();
                $end = CarbonImmutable::parse($endValue ?? $startValue, $workspace->timezone)->setTimezone($workspace->timezone)->endOfDay();
                $bar = $timeline->bar($start->min($end), $end->max($start));

                if ($bar === null) {
                    return null;
                }

                $total = (int) $project->timeline_total_tasks_count;

                return [
                    'public_id' => $project->public_id,
                    'name' => $project->name,
                    'status' => $project->status->label(),
                    'color' => $project->color ?: '#38bdf8',
                    'date_label' => $start->format('M j').' – '.$end->format('M j, Y'),
                    'progress' => $total ? (int) round($project->timeline_completed_tasks_count / $total * 100) : 0,
                    'context' => null,
                    'tasks' => $taskRows->where('group_id', $project->id)->values(),
                    ...$bar,
                ];
            })->filter()->values();

        return [$rows, $count];
    }

    /** @return array{0: Collection<int, array<string, mixed>>, 1: int} */
    private function featureRows(Workspace $workspace, ?int $departmentId, GanttTimeline $timeline, Collection $taskRows): array
    {
        $query = Feature::query()
            ->active()
            ->where('workspace_id', $workspace->id)
            ->whereHas('project', fn (Builder $project) => $project->where('status', '!=', ProjectStatus::ARCHIVED->value));

        if ($departmentId !== null) {
            $query->whereIn('features.id', $this->departmentFeatureIds($workspace, $departmentId));
        }

        $count = (clone $query)->count();
        $rows = $query
            ->addSelect([
                'timeline_first_task_at' => Task::query()
                    ->selectRaw('MIN(COALESCE(tasks.start_at, tasks.due_at))')
                    ->whereColumn('tasks.feature_id', 'features.id')
                    ->whereNull('tasks.archived_at'),
                'timeline_last_task_at' => Task::query()
                    ->selectRaw('MAX(COALESCE(tasks.due_at, tasks.start_at))')
                    ->whereColumn('tasks.feature_id', 'features.id')
                    ->whereNull('tasks.archived_at'),
            ])
            ->with('project:id,name,color')
            ->withCount([
                'tasks as timeline_total_tasks_count' => fn (Builder $tasks) => $tasks
                    ->whereNull('archived_at')
                    ->whereHas('status', fn (Builder $status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value)),
                'tasks as timeline_completed_tasks_count' => fn (Builder $tasks) => $tasks
                    ->whereNull('archived_at')
                    ->whereNotNull('completed_at')
                    ->whereHas('status', fn (Builder $status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value)),
            ])
            ->orderByRaw('COALESCE(features.starts_at, timeline_first_task_at)')
            ->limit(60)
            ->get()
            ->map(function (Feature $feature) use ($taskRows, $timeline, $workspace) {
                $startValue = $feature->starts_at?->toDateString() ?? $feature->timeline_first_task_at;
                $endValue = $feature->due_at?->toDateString() ?? $feature->timeline_last_task_at;

                if (! $startValue && ! $endValue) {
                    return null;
                }

                $start = CarbonImmutable::parse($startValue ?? $endValue, $workspace->timezone)->setTimezone($workspace->timezone)->startOfDay();
                $end = CarbonImmutable::parse($endValue ?? $startValue, $workspace->timezone)->setTimezone($workspace->timezone)->endOfDay();
                $bar = $timeline->bar($start->min($end), $end->max($start));

                if ($bar === null) {
                    return null;
                }

                $total = (int) $feature->timeline_total_tasks_count;

                return [
                    'public_id' => $feature->public_id,
                    'name' => $feature->name,
                    'status' => str($feature->status)->replace('_', ' ')->headline()->toString(),
                    'color' => $feature->project->color ?: '#38bdf8',
                    'date_label' => $start->format('M j').' – '.$end->format('M j, Y'),
                    'progress' => $total ? (int) round($feature->timeline_completed_tasks_count / $total * 100) : 0,
                    'context' => $feature->project->name,
                    'tasks' => $taskRows->where('group_id', $feature->id)->values(),
                    ...$bar,
                ];
            })->filter()->values();

        return [$rows, $count];
    }

    private function departmentProjectIds(Workspace $workspace, int $departmentId): Builder
    {
        return ProjectRequest::query()
            ->select('project_id')
            ->where('workspace_id', $workspace->id)
            ->where('requester_department_external_id', $departmentId)
            ->whereNotNull('project_id');
    }

    private function departmentFeatureIds(Workspace $workspace, int $departmentId): Builder
    {
        return FeatureRequest::query()
            ->select('feature_id')
            ->where('workspace_id', $workspace->id)
            ->where('requester_department_external_id', $departmentId)
            ->whereNotNull('feature_id');
    }
}
