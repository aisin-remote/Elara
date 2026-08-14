<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatusCategory;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\Task;
use App\Models\Workspace;
use App\Support\GanttTimeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class RequesterItTimeline
{
    private const TASK_LIMIT = 200;

    /** @return array<string, mixed> */
    public function build(Workspace $workspace, ?int $departmentId, ?string $scale): array
    {
        $timeline = new GanttTimeline($scale, $workspace->timezone);

        $taskQuery = Task::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->whereHas('project', fn (Builder $project) => $project
                ->delivery()
                ->where('status', '!=', ProjectStatus::ARCHIVED->value))
            ->whereHas('status', fn (Builder $status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value));

        if ($departmentId !== null) {
            $taskQuery->whereIn('tasks.project_id', $this->departmentProjectIds($workspace, $departmentId));
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

        $taskRows = $tasks->map(function (Task $task) use ($timeline, $workspace) {
            $start = CarbonImmutable::parse($task->start_at ?? $task->due_at)->setTimezone($workspace->timezone)->startOfDay();
            $end = CarbonImmutable::parse($task->due_at ?? $task->start_at)->setTimezone($workspace->timezone)->endOfDay();
            $bar = $timeline->bar($start->min($end), $end->max($start));

            if ($bar === null) {
                return null;
            }

            return [
                'project_id' => $task->project_id,
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

        $projectQuery = Project::query()
            ->delivery()
            ->where('workspace_id', $workspace->id)
            ->where('status', '!=', ProjectStatus::ARCHIVED->value);

        if ($departmentId !== null) {
            $projectQuery->whereIn('projects.id', $this->departmentProjectIds($workspace, $departmentId));
        }

        $projectCount = (clone $projectQuery)->count();
        $projects = $projectQuery
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
            ->get();

        $projectRows = $projects->map(function (Project $project) use ($taskRows, $timeline, $workspace) {
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
                'color' => $project->color,
                'date_label' => $start->format('M j').' – '.$end->format('M j, Y'),
                'progress' => $total ? (int) round($project->timeline_completed_tasks_count / $total * 100) : 0,
                'tasks' => $taskRows->where('project_id', $project->id)->values(),
                ...$bar,
            ];
        })->filter()->values();

        return [
            'timeline' => $timeline,
            'projectRows' => $projectRows,
            'projectCount' => $projectCount,
            'scheduledTaskCount' => $scheduledTaskCount,
            'hiddenTaskCount' => max(0, $scheduledTaskCount - $taskRows->count()),
            'updatedAt' => now($workspace->timezone),
        ];
    }

    private function departmentProjectIds(Workspace $workspace, int $departmentId): Builder
    {
        return ProjectRequest::query()
            ->select('project_id')
            ->where('workspace_id', $workspace->id)
            ->where('requester_department_external_id', $departmentId)
            ->whereNotNull('project_id');
    }
}
