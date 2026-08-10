<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use App\Support\GanttTimeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class RequesterItTimeline
{
    private const TASK_LIMIT = 200;

    /** @return array<string, mixed> */
    public function build(Workspace $workspace, ?string $scale): array
    {
        $timeline = new GanttTimeline($scale, $workspace->timezone);
        $contributingRoles = collect(WorkspaceRole::cases())
            ->filter(fn (WorkspaceRole $role) => $role->canContribute())
            ->pluck('value');
        $memberships = $workspace->memberships()
            ->active()
            ->whereIn('role', $contributingRoles)
            ->whereHas('user')
            ->with('user:id,public_id,first_name,last_name,avatar_path,job_title')
            ->get()
            ->sortBy(fn ($membership) => $membership->user->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $memberIds = $memberships->pluck('user_id');

        $taskQuery = Task::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->whereHas('project')
            ->whereHas('status', fn (Builder $status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value))
            ->whereHas('assignees', fn (Builder $assignees) => $assignees->whereIn('users.id', $memberIds))
            ->whereRaw('COALESCE(tasks.due_at, tasks.start_at) >= ?', [$timeline->from->utc()])
            ->whereRaw('COALESCE(tasks.start_at, tasks.due_at) <= ?', [$timeline->to->utc()]);
        $scheduledTaskCount = (clone $taskQuery)->count();
        $tasks = $taskQuery
            ->with([
                'project:id,public_id,name,color',
                'status:id,name,color,category',
                'assignees' => fn ($assignees) => $assignees
                    ->whereIn('users.id', $memberIds)
                    ->select('users.id', 'users.public_id', 'users.first_name', 'users.last_name', 'users.avatar_path'),
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
                'title' => $task->title,
                'project' => $task->project->name,
                'status' => $task->status->name,
                'color' => $task->status->color ?: $task->project->color,
                'date_label' => $start->isSameDay($end) ? $start->format('M j') : $start->format('M j').' – '.$end->format('M j'),
                'progress' => $task->checklist_total
                    ? (int) round($task->checklist_completed / $task->checklist_total * 100)
                    : ($task->completed_at ? 100 : 0),
                'is_overdue' => $task->completed_at === null && $task->due_at?->isPast(),
                'assignee_ids' => $task->assignees->pluck('id')->all(),
                ...$bar,
            ];
        })->filter()->values();

        $members = $memberships->map(function ($membership) use ($taskRows) {
            $rows = $taskRows->filter(fn (array $row) => in_array($membership->user_id, $row['assignee_ids'], true))->values();

            return [
                'name' => $membership->user->name,
                'public_id' => $membership->user->public_id,
                'has_avatar' => filled($membership->user->avatar_path),
                'job_title' => $membership->user->job_title,
                'role' => $membership->role === WorkspaceRole::MANAGER ? 'Leader' : $membership->role->label(),
                'tasks' => $rows,
            ];
        })->values();

        $projectQuery = Project::query()
            ->delivery()
            ->where('workspace_id', $workspace->id)
            ->where('status', '!=', ProjectStatus::ARCHIVED->value);
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

        $projectRows = $projects->map(function (Project $project) use ($timeline, $workspace) {
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
                'name' => $project->name,
                'status' => $project->status->label(),
                'color' => $project->color,
                'date_label' => $start->format('M j').' – '.$end->format('M j, Y'),
                'progress' => $total ? (int) round($project->timeline_completed_tasks_count / $total * 100) : 0,
                ...$bar,
            ];
        })->filter()->values();

        return [
            'timeline' => $timeline,
            'projectRows' => $projectRows,
            'members' => $members,
            'projectCount' => $projectCount,
            'scheduledTaskCount' => $scheduledTaskCount,
            'hiddenTaskCount' => max(0, $scheduledTaskCount - $taskRows->count()),
            'updatedAt' => now($workspace->timezone),
        ];
    }
}
