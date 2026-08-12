<?php

namespace App\Http\Controllers\App;

use App\Enums\TaskPriority;
use App\Enums\TaskPropertyType;
use App\Enums\TaskStatusCategory;
use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskProperty;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OrganizationDirectory;
use App\Services\TaskFieldSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function global(Request $request, Workspace $workspace, OrganizationDirectory $organization): View
    {
        $this->authorize('view', $workspace);
        $members = $organization->taskMembers($request->user(), $workspace)
            ->sortBy(fn (User $user) => $user->is($request->user()) ? 0 : 1)
            ->values();
        $selectedMember = $members->firstWhere(
            'public_id',
            $request->string('assignee')->toString() ?: $request->user()->public_id,
        );
        abort_unless($selectedMember, 404);

        $tasks = Task::query()
            ->visibleTo($request->user())
            ->where('workspace_id', $workspace->id)
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

    public function project(Request $request, Workspace $workspace, Project $project, TaskFieldSchema $fieldSchema): View
    {
        $this->ensureProjectWorkspace($workspace, $project);
        $this->authorize('viewAny', [Task::class, $project]);
        $selectedFeature = $this->selectedFeature($request, $project);
        $statuses = $project->taskStatuses()
            ->active()
            ->withCount(['tasks' => fn (Builder $tasks) => $tasks->withTrashed()])
            ->get();
        $tasks = $project->tasks()
            ->visibleTo($request->user())
            ->with([
                'status', 'category', 'assignees', 'milestone', 'propertyValues',
                'dependencies' => fn ($dependencies) => $dependencies->visibleTo($request->user()),
            ])
            ->when($selectedFeature, fn (Builder $query, Feature $feature) => $query->where('feature_id', $feature->id))
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($request->string('priority')->toString(), fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($request->boolean('blocked'), fn (Builder $query) => $query->blocked())
            ->orderBy('position')
            ->paginate(50)
            ->withQueryString();

        $properties = $project->taskProperties()->active()->get();
        $systemFields = $fieldSchema->systemFields($project);
        $taskFields = $fieldSchema->visibleFields($project, $properties);
        $groupByOptions = collect([['key' => 'status', 'name' => 'Workflow status']])
            ->concat($taskFields
                ->where('type', TaskPropertyType::SELECT->value)
                ->map(fn (array $field): array => [
                    'key' => $field['kind'] === 'system' ? $field['key'] : 'property:'.$field['property']->public_id,
                    'name' => $field['name'],
                ]))
            ->values();
        $requestedGroupBy = $request->string('group_by')->toString() ?: 'status';
        $groupBy = $groupByOptions->contains('key', $requestedGroupBy) ? $requestedGroupBy : 'status';
        $groupProperty = str_starts_with($groupBy, 'property:')
            ? $properties->firstWhere('public_id', substr($groupBy, strlen('property:')))
            : null;

        return view('app.tasks.project-list', [
            'workspace' => $workspace,
            'project' => $project,
            'statuses' => $statuses,
            'tasks' => $tasks,
            'taskGroups' => $this->taskGroups($tasks->getCollection(), $statuses, $groupBy, $groupProperty),
            'groupBy' => $groupBy,
            'groupByOptions' => $groupByOptions,
            'properties' => $properties,
            'systemFields' => $systemFields,
            'taskFields' => $taskFields,
            'archivedTasks' => $project->tasks()->onlyTrashed()
                ->visibleTo($request->user())
                ->when($selectedFeature, fn (Builder $query, Feature $feature) => $query->where('feature_id', $feature->id))
                ->with('status')->latest('archived_at')->get(),
            'selectedFeature' => $selectedFeature,
            ...$this->formData($workspace, $project, $request->user()),
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

    public function show(Task $task): View
    {
        $this->authorize('view', $task);
        $task->load([
            'workspace', 'project.taskStatuses', 'status', 'category', 'milestone', 'assignees',
            'checklistItems', 'comments.author', 'files.uploader',
        ]);

        return view('app.tasks.show', [
            'task' => $task,
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

    private function taskGroups(Collection $tasks, Collection $statuses, string $groupBy, ?TaskProperty $property): Collection
    {
        if ($groupBy === 'status') {
            return $statuses->map(fn ($status): array => [
                'key' => 'status:'.$status->public_id,
                'name' => $status->name,
                'color' => $status->color,
                'status' => $status,
                'tasks' => $tasks->where('status_id', $status->id)->values(),
                'defaults' => ['status_public_id' => $status->public_id],
            ]);
        }

        if ($groupBy === 'priority') {
            $colors = ['low' => '#64748b', 'medium' => '#0ea5e9', 'high' => '#f59e0b', 'urgent' => '#f43f5e'];

            return collect(TaskPriority::cases())->map(fn (TaskPriority $priority): array => [
                'key' => 'priority:'.$priority->value,
                'name' => $priority->label(),
                'color' => $colors[$priority->value],
                'status' => null,
                'tasks' => $tasks->where('priority', $priority)->values(),
                'defaults' => ['priority' => $priority->value],
            ]);
        }

        abort_unless($property, 404);
        $colors = ['#6366f1', '#0ea5e9', '#14b8a6', '#f59e0b', '#f43f5e', '#8b5cf6'];
        $options = collect($property->options_json ?? []);
        $value = fn (Task $task): mixed => $task->propertyValues
            ->firstWhere('task_property_id', $property->id)?->value_json;
        $groups = $options->values()->map(fn (string $option, int $index): array => [
            'key' => 'property:'.$property->public_id.':'.$index,
            'name' => $option,
            'color' => $colors[$index % count($colors)],
            'status' => null,
            'tasks' => $tasks->filter(fn (Task $task): bool => $value($task) === $option)->values(),
            'defaults' => ['property_values['.$property->public_id.']' => $option],
        ]);
        $withoutSelection = $tasks->reject(fn (Task $task): bool => $options->contains($value($task)))->values();

        return $withoutSelection->isEmpty() ? $groups : $groups->push([
            'key' => 'property:'.$property->public_id.':empty',
            'name' => 'No selection',
            'color' => '#94a3b8',
            'status' => null,
            'tasks' => $withoutSelection,
            'defaults' => ['property_values['.$property->public_id.']' => ''],
        ]);
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
