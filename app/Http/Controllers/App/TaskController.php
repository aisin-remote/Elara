<?php

namespace App\Http\Controllers\App;

use App\Enums\DependencyType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function global(Request $request, Workspace $workspace): View
    {
        $this->authorize('view', $workspace);
        $tasks = Task::query()
            ->visibleTo($request->user())
            ->where('workspace_id', $workspace->id)
            ->with(['project', 'status', 'category', 'assignees', 'milestone', 'dependencies'])
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($request->string('project')->toString(), fn (Builder $query, string $projectId) => $query->whereHas('project', fn (Builder $project) => $project->where('public_id', $projectId)))
            ->when($request->string('priority')->toString(), fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($request->string('assignee')->toString(), fn (Builder $query, string $userId) => $query->whereHas('assignees', fn (Builder $users) => $users->where('users.public_id', $userId)))
            ->when($request->string('tab')->toString(), fn (Builder $query, string $tab) => $this->applyTab($query, $tab))
            ->orderByRaw('due_at IS NULL, due_at')
            ->paginate(25)
            ->withQueryString();

        return view('app.tasks.global', [
            'workspace' => $workspace,
            'tasks' => $tasks,
            'projects' => $workspace->projects()->visibleTo($request->user())->orderBy('name')->get(),
            'members' => $workspace->memberships()->active()->with('user')->get(),
            'priorities' => TaskPriority::cases(),
        ]);
    }

    public function project(Request $request, Workspace $workspace, Project $project): View
    {
        $this->ensureProjectWorkspace($workspace, $project);
        $this->authorize('viewAny', [Task::class, $project]);
        $statuses = $project->taskStatuses()->active()->get();
        $tasks = $project->tasks()
            ->with(['status', 'category', 'assignees', 'milestone', 'dependencies'])
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($request->string('priority')->toString(), fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($request->boolean('blocked'), fn (Builder $query) => $query->blocked())
            ->orderBy('position')
            ->paginate(50)
            ->withQueryString();

        return view('app.tasks.project-list', [
            'workspace' => $workspace,
            'project' => $project,
            'statuses' => $statuses,
            'tasks' => $tasks,
            'tasksByStatus' => $tasks->getCollection()->groupBy('status_id'),
            'archivedTasks' => $project->tasks()->onlyTrashed()->with('status')->latest('archived_at')->get(),
            ...$this->formData($workspace, $project),
        ]);
    }

    public function board(Workspace $workspace, Project $project): View
    {
        $this->ensureProjectWorkspace($workspace, $project);
        $this->authorize('viewAny', [Task::class, $project]);
        $statuses = $project->taskStatuses()->active()->with(['tasks' => fn ($query) => $query
            ->with(['category', 'assignees', 'checklistItems', 'milestone', 'dependencies'])
            ->orderBy('position')])->get();

        return view('app.tasks.board', [
            'workspace' => $workspace,
            'project' => $project,
            'statuses' => $statuses,
            ...$this->formData($workspace, $project),
        ]);
    }

    public function show(Task $task): View
    {
        $this->authorize('view', $task);
        $task->load([
            'workspace', 'project.taskStatuses', 'status', 'category', 'milestone', 'assignees',
            'dependencies.status', 'dependencies.project', 'dependents.status', 'checklistItems', 'comments.author',
            'files.uploader', 'timeEntries.user',
        ]);

        $linkedIds = $task->dependencies->modelKeys();

        return view('app.tasks.show', [
            'task' => $task,
            'dependencyTypes' => DependencyType::cases(),
            'dependencyCandidates' => Task::query()
                ->where('workspace_id', $task->workspace_id)
                ->whereNull('archived_at')
                ->whereKeyNot($task->id)
                ->whereNotIn('id', $linkedIds)
                ->with('project:id,name')
                ->orderBy('title')
                ->limit(200)
                ->get(['id', 'public_id', 'title', 'project_id']),
            'loggedMinutes' => $task->loggedMinutes(),
            ...$this->formData($task->workspace, $task->project),
        ]);
    }

    private function formData(Workspace $workspace, Project $project): array
    {
        return [
            'categories' => $workspace->taskCategories()->orderBy('name')->get(),
            'projectMembers' => $project->memberships()->with('user')->get(),
            'priorities' => TaskPriority::cases(),
            'milestones' => $project->milestones()->get(),
        ];
    }

    private function ensureProjectWorkspace(Workspace $workspace, Project $project): void
    {
        abort_unless($project->workspace_id === $workspace->id, 404);
    }

    private function applyTab(Builder $query, string $tab): void
    {
        match ($tab) {
            'todo' => $query->whereHas('status', fn (Builder $status) => $status->whereIn('category', [TaskStatusCategory::BACKLOG->value, TaskStatusCategory::TODO->value])),
            'in_progress' => $query->whereHas('status', fn (Builder $status) => $status->where('category', TaskStatusCategory::IN_PROGRESS->value)),
            'completed' => $query->whereNotNull('completed_at'),
            'overdue' => $query->whereNull('completed_at')->where('due_at', '<', now()),
            'blocked' => $query->blocked(),
            default => null,
        };
    }
}
