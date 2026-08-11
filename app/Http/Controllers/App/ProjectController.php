<?php

namespace App\Http\Controllers\App;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Planning\CriticalPathAnalyzer;
use App\Services\Planning\ForecastHealthService;
use App\Support\GanttTimeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request, Workspace $workspace): View
    {
        $this->authorize('viewAny', [Project::class, $workspace]);

        $search = trim($request->string('search'));
        $status = $request->string('status')->toString();
        $projects = $workspace->projects()
            ->delivery()
            ->visibleTo($request->user())
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($status && ProjectStatus::tryFrom($status), fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $archivedProjects = $workspace->projects()
            ->delivery()
            ->onlyTrashed()
            ->visibleTo($request->user())
            ->orderBy('name')
            ->get();

        return view('app.projects.index', compact('workspace', 'projects', 'archivedProjects', 'search', 'status'));
    }

    public function create(Workspace $workspace): View
    {
        $this->authorize('create', [Project::class, $workspace]);

        return view('app.projects.create', [
            'workspace' => $workspace,
            'availableMembers' => $workspace->memberships()->active()->with('user')->orderBy('id')->get(),
            'existingProjects' => $workspace->projects()
                ->delivery()
                ->visibleTo(request()->user())
                ->with('members:id,public_id,first_name,last_name,avatar_path')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Project $project, ForecastHealthService $forecast): View
    {
        $this->authorize('view', $project);
        $project->load(['workspace', 'owner', 'memberships.user']);
        $assignedUserIds = $project->memberships->pluck('user_id');

        $progress = $project->taskProgress();

        return view('app.projects.show', [
            'project' => $project,
            'eligibleTaskCount' => $progress['total'],
            'completedTaskCount' => $progress['completed'],
            'progress' => $progress['percentage'],
            'overdueTaskCount' => $progress['overdue'],
            'taskBuckets' => $progress['buckets'],
            'schedule' => $project->scheduleHealth($progress['percentage']),
            'forecast' => $forecast->forProject($project),
            'breakdown' => $project->breakdowns()->with('acceptedBy')->latest('id')->first(),
            'availableMembers' => $project->workspace->memberships()
                ->active()
                ->whereNotIn('user_id', $assignedUserIds)
                ->with('user')
                ->get(),
        ]);
    }

    public function timeline(Request $request, Workspace $workspace, Project $project, CriticalPathAnalyzer $criticalPath): View
    {
        abort_unless($project->workspace_id === $workspace->id, 404);
        $this->authorize('view', $project);

        $timeline = new GanttTimeline($request->string('scale')->toString(), $workspace->timezone);
        $path = $criticalPath->forProject($project);
        $criticalIds = array_flip($path['critical_public_ids']);
        $slackByPublicId = collect($path['tasks'])->keyBy('public_id');

        $tasks = $project->tasks()
            ->whereNull('archived_at')
            ->with(['status', 'assignees', 'milestone', 'dependencies'])
            ->withCount([
                'checklistItems as checklist_total',
                'checklistItems as checklist_completed' => fn (Builder $query) => $query->where('is_completed', true),
            ])
            ->orderByRaw('coalesce(start_at, due_at) is null, coalesce(start_at, due_at) asc')
            ->limit(60)
            ->get();

        [$scheduled, $unscheduled] = $tasks->partition(fn (Task $task) => $task->start_at || $task->due_at);

        $rows = $scheduled->map(function (Task $task) use ($timeline, $workspace, $criticalIds, $slackByPublicId) {
            $start = CarbonImmutable::parse($task->start_at ?? $task->due_at)->setTimezone($workspace->timezone)->startOfDay();
            $end = CarbonImmutable::parse($task->due_at ?? $task->start_at)->setTimezone($workspace->timezone)->endOfDay();
            $bar = $timeline->bar($start->min($end), $end->max($start));
            $pathRow = $slackByPublicId->get($task->public_id);

            return $bar === null ? null : [
                'public_id' => $task->public_id,
                'title' => $task->title,
                'color' => $task->status->color ?: '#64748b',
                'status' => $task->status->name,
                'date_label' => $start->isSameDay($end) ? $start->format('M j') : $start->format('M j').' – '.$end->format('M j'),
                'progress' => $task->checklist_total
                    ? (int) round($task->checklist_completed / $task->checklist_total * 100)
                    : ($task->completed_at ? 100 : 0),
                'is_overdue' => $task->completed_at === null && $task->due_at && $task->due_at->isPast(),
                'is_blocked' => $task->isBlocked(),
                'is_critical' => isset($criticalIds[$task->public_id]),
                'slack' => $pathRow['slack'] ?? null,
                'baseline_label' => ($task->baseline_start_at || $task->baseline_due_at)
                    ? trim(($task->baseline_start_at?->format('M j') ?? '').' – '.($task->baseline_due_at?->format('M j') ?? ''), ' –')
                    : null,
                'milestone' => $task->milestone?->name,
                'dependencies' => $task->dependencies->map(fn (Task $dependency) => [
                    'public_id' => $dependency->public_id,
                    'title' => $dependency->title,
                    'completed' => $dependency->completed_at !== null,
                    'type' => $dependency->pivot->type ?? 'fs',
                ])->all(),
                'assignees' => $task->assignees->take(3)->map(fn (User $user) => [
                    'name' => $user->name,
                    'has_avatar' => filled($user->avatar_path),
                    'public_id' => $user->public_id,
                ])->all(),
                ...$bar,
            ];
        })->filter()->values();

        $milestones = $project->milestones()->withCount('tasks')->get();
        $milestoneRows = $milestones->map(function ($milestone) use ($timeline, $workspace) {
            $target = CarbonImmutable::parse($milestone->target_date, $workspace->timezone)->startOfDay();
            $marker = $timeline->bar($target, $target->endOfDay());

            return $marker === null ? null : [
                'public_id' => $milestone->public_id,
                'name' => $milestone->name,
                'target_date' => $milestone->target_date->format('M j, Y'),
                'completed' => $milestone->completed_at !== null,
                'task_count' => $milestone->tasks_count,
                'left' => $marker['left'],
            ];
        })->filter()->values();

        $rowIndexes = $rows->mapWithKeys(fn (array $row, int $index) => [$row['public_id'] => $index]);
        $taskOffset = $milestoneRows->count();
        $dependencyLines = $rows->flatMap(function (array $row, int $toIndex) use ($rows, $rowIndexes, $taskOffset) {
            return collect($row['dependencies'])->map(function (array $dependency) use ($row, $rows, $rowIndexes, $toIndex, $taskOffset) {
                $fromIndex = $rowIndexes->get($dependency['public_id']);
                if ($fromIndex === null) {
                    return null;
                }

                $from = $rows[$fromIndex];

                return [
                    'from_x' => min(100, $from['left'] + $from['width']),
                    'from_y' => ($taskOffset + $fromIndex) * 56 + 28,
                    'to_x' => $row['left'],
                    'to_y' => ($taskOffset + $toIndex) * 56 + 28,
                    'completed' => $dependency['completed'],
                    'critical' => ($row['is_critical'] ?? false) && ($from['is_critical'] ?? false),
                ];
            })->filter();
        })->values();

        return view('app.projects.timeline', [
            'workspace' => $workspace,
            'project' => $project,
            'timeline' => $timeline,
            'rows' => $rows,
            'milestones' => $milestones,
            'milestoneRows' => $milestoneRows,
            'dependencyLines' => $dependencyLines,
            'chartRowCount' => $milestoneRows->count() + $rows->count(),
            'hiddenCount' => $scheduled->count() - $rows->count(),
            'hiddenMilestoneCount' => $milestones->count() - $milestoneRows->count(),
            'unscheduled' => $unscheduled,
            'statuses' => $project->taskStatuses()->active()->get(),
            'categories' => $workspace->taskCategories()->orderBy('name')->get(),
            'projectMembers' => $project->memberships()->with('user')->get(),
            'priorities' => TaskPriority::cases(),
            'criticalCount' => count($path['critical_public_ids']),
            'projectedFinish' => $path['projected_finish']?->format('M j, Y'),
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('app.projects.edit', compact('project'));
    }
}
