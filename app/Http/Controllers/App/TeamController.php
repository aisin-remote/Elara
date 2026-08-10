<?php

namespace App\Http\Controllers\App;

use App\Enums\WorkspaceMemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamFilterRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(TeamFilterRequest $request, Workspace $workspace): View
    {
        $filters = $request->validated();
        $projects = Project::query()->visibleTo($request->user())->where('workspace_id', $workspace->id)->orderBy('name')->get();
        $project = $projects->firstWhere('public_id', $filters['project'] ?? null);
        abort_if(($filters['project'] ?? null) && ! $project, 404);
        $visibleProjectIds = $projects->pluck('id');
        $activeSince = now()->subMinutes(5);

        return view('app.team.index', [
            'workspace' => $workspace,
            'memberships' => $workspace->memberships()->with('user')
                ->whereHas('user')
                ->when($workspace->organization_department_id !== null, fn ($members) => $members->active())
                ->when($filters['search'] ?? null, fn ($members, string $search) => $members->whereHas('user', fn ($user) => $user->where(fn ($identity) => $identity
                    ->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))))
                ->when($filters['role'] ?? null, fn ($members, string $role) => $members->where('role', $role))
                ->when(($filters['presence'] ?? null) === 'active', fn ($members) => $members->whereHas('user', fn ($user) => $user->where('last_seen_at', '>=', $activeSince)))
                ->when(($filters['presence'] ?? null) === 'offline', fn ($members) => $members->whereHas('user', fn ($user) => $user->where(fn ($presence) => $presence->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $activeSince))))
                ->when($project, fn ($members) => $members->whereHas('user.projectMemberships', fn ($memberships) => $memberships->where('project_id', $project->id)))
                ->with(['user' => fn ($users) => $users
                    ->withCount(['assignedTasks as active_tasks_count' => fn ($tasks) => $tasks->visibleTo($request->user())->where('tasks.workspace_id', $workspace->id)->whereNull('completed_at')])
                    ->with(['projectMemberships' => fn ($memberships) => $memberships->whereIn('project_id', $visibleProjectIds)->with('project')])])
                ->orderBy('id')->get(),
            'invitations' => $workspace->invitations()
                ->whereNull('accepted_at')
                ->whereNull('rejected_at')
                ->latest('id')
                ->get(),
            'projects' => $projects,
            'ownershipCandidates' => $workspace->memberships()->active()->whereHas('user')->with('user')->where('user_id', '!=', $request->user()->id)->orderBy('id')->get(),
            'summary' => [
                'members' => $workspace->memberships()->active()->whereHas('user')->count(),
                'online' => $workspace->memberships()->active()->whereHas('user', fn ($users) => $users->where('last_seen_at', '>=', $activeSince))->count(),
                'tasks' => Task::query()->visibleTo($request->user())->where('workspace_id', $workspace->id)->whereNull('completed_at')->count(),
            ],
        ]);
    }

    public function show(Request $request, Workspace $workspace, WorkspaceMember $member): View
    {
        abort_unless(
            $member->workspace_id === $workspace->id
                && ($workspace->organization_department_id === null || $member->status === WorkspaceMemberStatus::ACTIVE)
                && $member->user()->exists(),
            404,
        );
        $this->authorize('view', $workspace);

        $projects = Project::query()->visibleTo($request->user())->where('workspace_id', $workspace->id)
            ->whereHas('memberships', fn ($memberships) => $memberships->where('user_id', $member->user_id))
            ->withCount(['tasks as assigned_tasks_count' => fn ($tasks) => $tasks->whereHas('assignees', fn ($users) => $users->where('users.id', $member->user_id))])
            ->orderBy('name')->get();
        $tasks = Task::query()->visibleTo($request->user())->where('workspace_id', $workspace->id)
            ->whereHas('assignees', fn ($users) => $users->where('users.id', $member->user_id));

        return view('app.team.show', [
            'workspace' => $workspace,
            'membership' => $member->load('user'),
            'projects' => $projects,
            'stats' => [
                'assigned' => (clone $tasks)->count(),
                'completed' => (clone $tasks)->whereNotNull('completed_at')->count(),
                'overdue' => (clone $tasks)->whereNull('completed_at')->where('due_at', '<', now())->count(),
                'active' => (clone $tasks)->whereNull('completed_at')->count(),
            ],
            'tasks' => $tasks->with(['project', 'status'])->latest('updated_at')->paginate(10)->withQueryString(),
            'activity' => $workspace->activityLogs()->where('actor_id', $member->user_id)->latest('created_at')->limit(8)->get(),
        ]);
    }
}
