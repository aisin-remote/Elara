<?php

namespace App\Http\Controllers\App;

use App\Enums\ProjectType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OrganizationDirectory;
use App\Services\PersonalTaskSpace;
use App\Services\RequestTaskAccess;
use App\Services\TaskDatabaseView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function global(
        Request $request,
        Workspace $workspace,
        OrganizationDirectory $organization,
        PersonalTaskSpace $personalTasks,
        TaskDatabaseView $database,
    ): View {
        $this->authorize('view', $workspace);
        $members = $organization->taskMembers($request->user(), $workspace)
            ->sortBy(fn (User $user) => $user->is($request->user()) ? 0 : 1)
            ->values();
        $selectedMember = $members->firstWhere(
            'public_id',
            $request->string('assignee')->toString() ?: $request->user()->public_id,
        );
        abort_unless($selectedMember, 404);

        if ($selectedMember->is($request->user()) && $request->string('view')->toString() !== 'assigned') {
            $project = $personalTasks->for($workspace, $request->user());
            $this->authorize('viewAny', [Task::class, $project]);

            return view('app.tasks.personal', [
                'workspace' => $workspace,
                'project' => $project,
                ...$database->data($request, $workspace, $project, $request->user()),
            ]);
        }

        $tasks = Task::query()
            ->visibleTo($request->user())
            ->where('workspace_id', $workspace->id)
            ->whereHas('project', fn (Builder $project) => $project
                ->where('type', '!=', ProjectType::PERSONAL->value))
            ->whereHas('assignees', fn (Builder $users) => $users->where('users.id', $selectedMember->id))
            ->with([
                'project', 'status', 'category', 'assignees', 'milestone',
                'dependencies' => fn ($dependencies) => $dependencies->visibleTo($request->user()),
            ])
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($request->string('project')->toString(), fn (Builder $query, string $projectId) => $query->whereHas('project', fn (Builder $project) => $project->where('public_id', $projectId)))
            ->when($request->string('priority')->toString(), fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($request->string('tab')->toString(), fn (Builder $query, string $tab) => $this->applyTab($query, $tab))
            ->orderByRaw('due_at IS NULL, due_at')
            ->paginate(25)
            ->withQueryString();

        return view('app.tasks.global', [
            'workspace' => $workspace,
            'tasks' => $tasks,
            'projects' => $workspace->projects()->visibleTo($request->user())->orderBy('name')->get(),
            'members' => $members,
            'selectedMember' => $selectedMember,
            'priorities' => TaskPriority::cases(),
        ]);
    }

    public function project(Request $request, Workspace $workspace, Project $project, TaskDatabaseView $database): View
    {
        $this->ensureProjectWorkspace($workspace, $project);
        $this->authorize('viewAny', [Task::class, $project]);
        $selectedFeature = $this->selectedFeature($request, $project);

        return view('app.tasks.project-list', [
            'workspace' => $workspace,
            'project' => $project,
            ...$database->data($request, $workspace, $project, $request->user(), $selectedFeature),
        ]);
    }

    public function board(Request $request, Workspace $workspace, Project $project): RedirectResponse
    {
        $this->ensureProjectWorkspace($workspace, $project);
        $this->authorize('viewAny', [Task::class, $project]);

        return redirect()->route('app.projects.tasks', [
            'workspace' => $workspace,
            'project' => $project,
            ...$request->only('feature'),
        ]);
    }

    public function show(Task $task, RequestTaskAccess $requestTasks): View
    {
        $this->authorize('view', $task);
        $task->load([
            'workspace', 'project.taskStatuses', 'status', 'category', 'milestone', 'assignees',
            'checklistItems', 'comments.author', 'files.uploader',
        ]);

        return view('app.tasks.show', [
            'task' => $task,
            'requestContext' => $requestTasks->visibleRequest(request()->user(), $task),
            ...$this->formData($task->workspace, $task->project, request()->user()),
        ]);
    }

    private function formData(Workspace $workspace, Project $project, User $viewer): array
    {
        $visibleUserIds = app(OrganizationDirectory::class)->taskMembers($viewer, $workspace)->pluck('id');

        return [
            'categories' => $workspace->taskCategories()->orderBy('name')->get(),
            'projectMembers' => $project->memberships()->whereIn('user_id', $visibleUserIds)->with('user')->get(),
            'priorities' => TaskPriority::cases(),
            'milestones' => $project->milestones()->get(),
            'features' => $project->features()->active()->orderBy('name')->get(),
        ];
    }

    private function ensureProjectWorkspace(Workspace $workspace, Project $project): void
    {
        abort_unless($project->workspace_id === $workspace->id, 404);
    }

    private function selectedFeature(Request $request, Project $project): ?Feature
    {
        $publicId = $request->string('feature')->toString();

        return $publicId === ''
            ? null
            : $project->features()->where('public_id', $publicId)->firstOrFail();
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
