<?php

namespace App\Http\Controllers\App;

use App\Enums\SupportingTaskCategory;
use App\Enums\SupportingTaskStatus;
use App\Enums\TaskPriority;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\SupportingTask;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportingTaskController extends Controller
{
    public function index(Request $request, Workspace $workspace): View
    {
        $this->authorize('viewAny', [SupportingTask::class, $workspace]);

        $search = trim($request->string('search')->toString());
        $status = SupportingTaskStatus::tryFrom($request->string('status')->toString());
        $category = SupportingTaskCategory::tryFrom($request->string('category')->toString());
        $assignee = $request->string('assignee')->toString();
        $today = today($workspace->timezone)->toDateString();
        $base = $workspace->supportingTasks();

        $tasks = (clone $base)
            ->with(['creator', 'assignee', 'workspace'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%')))
            ->when($status, fn (Builder $query, SupportingTaskStatus $value) => $query->where('status', $value->value))
            ->when($category, fn (Builder $query, SupportingTaskCategory $value) => $query->where('category', $value->value))
            ->when($assignee !== '', fn (Builder $query) => $query->whereHas('assignee', fn (Builder $user) => $user->where('public_id', $assignee)))
            ->orderByRaw("case status when 'in_progress' then 0 when 'todo' then 1 when 'completed' then 2 else 3 end")
            ->orderByRaw('due_date is null, due_date')
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('app.supporting.index', [
            'workspace' => $workspace,
            'tasks' => $tasks,
            'statuses' => SupportingTaskStatus::cases(),
            'categories' => SupportingTaskCategory::cases(),
            'priorities' => TaskPriority::cases(),
            'members' => $this->contributors($workspace),
            'stats' => [
                'open' => (clone $base)->whereIn('status', [SupportingTaskStatus::TODO->value, SupportingTaskStatus::IN_PROGRESS->value])->count(),
                'in_progress' => (clone $base)->where('status', SupportingTaskStatus::IN_PROGRESS->value)->count(),
                'overdue' => (clone $base)->whereIn('status', [SupportingTaskStatus::TODO->value, SupportingTaskStatus::IN_PROGRESS->value])->where('due_date', '<', $today)->count(),
                'completed' => (clone $base)->where('status', SupportingTaskStatus::COMPLETED->value)->count(),
            ],
        ]);
    }

    public function create(Workspace $workspace): View
    {
        $this->authorize('create', [SupportingTask::class, $workspace]);

        return view('app.supporting.form', [
            'workspace' => $workspace,
            'task' => null,
            ...$this->formData($workspace),
        ]);
    }

    public function edit(Workspace $workspace, SupportingTask $supportingTask): View
    {
        abort_unless($supportingTask->workspace_id === $workspace->id, 404);
        $this->authorize('update', $supportingTask);

        return view('app.supporting.form', [
            'workspace' => $workspace,
            'task' => $supportingTask,
            ...$this->formData($workspace),
        ]);
    }

    private function formData(Workspace $workspace): array
    {
        return [
            'statuses' => SupportingTaskStatus::cases(),
            'categories' => SupportingTaskCategory::cases(),
            'priorities' => TaskPriority::cases(),
            'members' => $this->contributors($workspace),
        ];
    }

    private function contributors(Workspace $workspace)
    {
        $roles = collect(WorkspaceRole::cases())
            ->filter(fn (WorkspaceRole $role) => $role->canContribute())
            ->pluck('value');

        return $workspace->memberships()->active()
            ->whereIn('role', $roles)
            ->with('user')
            ->get()
            ->sortBy(fn ($membership) => $membership->user->name)
            ->values();
    }
}
