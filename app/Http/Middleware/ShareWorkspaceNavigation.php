<?php

namespace App\Http\Middleware;

use App\Enums\BreakdownStatus;
use App\Enums\OrganizationRankGroup;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\TaskBreakdown;
use App\Models\Workspace;
use App\Services\OrganizationDirectory;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareWorkspaceNavigation
{
    public function __construct(private readonly OrganizationDirectory $organization) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspaces = $request->user()->workspaceMemberships()
            ->active()
            ->with('workspace')
            ->orderBy('id')
            ->get()
            ->pluck('workspace')
            ->filter();

        $routeWorkspace = $request->route('workspace');
        $routeProject = $request->route('project');
        $activeWorkspace = $routeWorkspace instanceof Workspace
            ? $routeWorkspace
            : ($routeProject instanceof Project ? $routeProject->workspace : null);

        if (! $activeWorkspace) {
            $activeId = $request->session()->get('active_workspace_id');
            $activeWorkspace = $workspaces->firstWhere('id', $activeId) ?? $workspaces->first();
        }

        if ($activeWorkspace) {
            $request->session()->put('active_workspace_id', $activeWorkspace->id);
        }

        $deliverySidebar = $activeWorkspace && str_starts_with($request->path(), 'app');

        $sidebarProjects = $deliverySidebar
            ? Project::query()
                ->delivery()
                ->visibleTo($request->user())
                ->where('workspace_id', $activeWorkspace->id)
                ->orderBy('name')
                ->limit(10)
                ->get()
            : collect();

        $sidebarMembers = $deliverySidebar
            ? $activeWorkspace->memberships()
                ->active()
                ->whereHas('user')
                ->where('user_id', '!=', $request->user()->id)
                ->with('user')
                ->orderBy('id')
                ->limit(8)
                ->get()
            : collect();

        $sidebarTaskMembers = $deliverySidebar
            ? $this->organization->taskMembers($request->user(), $activeWorkspace)
                ->sortBy(fn ($member) => ($member->is($request->user()) ? '0' : '1').strtolower($member->name))
                ->values()
            : collect();

        $sidebarTeamTaskMembers = $deliverySidebar
            ? $this->organization->teamSidebarMembers($request->user(), $activeWorkspace)
                ->sortBy(fn ($member) => strtolower($member->name))
                ->take(8)
                ->values()
            : collect();

        $pendingApprovals = 0;

        if ($deliverySidebar && $request->user()->can('viewAny', [FeatureRequest::class, $activeWorkspace])) {
            $pendingApprovals = FeatureRequest::query()
                ->where('workspace_id', $activeWorkspace->id)
                ->awaitingReview()
                ->count()
                + ProjectRequest::query()
                    ->where('workspace_id', $activeWorkspace->id)
                    ->awaitingDecision()
                    ->count()
                + TaskBreakdown::query()
                    ->where('workspace_id', $activeWorkspace->id)
                    ->where('status', BreakdownStatus::READY->value)
                    ->count();
        }

        $organizationProfile = null;
        $canApproveDepartmentRequests = false;
        $departmentApprovalCount = 0;

        if ($activeWorkspace && str_starts_with($request->path(), 'desk')) {
            $organizationProfile = $this->organization->profile($request->user());
            $canApproveDepartmentRequests = $organizationProfile !== null
                && $organizationProfile['rank_group'] === OrganizationRankGroup::MANAGEMENT
                && strcasecmp($organizationProfile['department_code'], config('organization.it_department_code')) !== 0;

            if ($canApproveDepartmentRequests) {
                $departmentApprovalCount = FeatureRequest::query()
                    ->when($activeWorkspace->organization_department_id === null, fn ($query) => $query->where('workspace_id', $activeWorkspace->id))
                    ->where('requester_department_external_id', $organizationProfile['department_id'])
                    ->awaitingDepartment()
                    ->count()
                    + ProjectRequest::query()
                        ->when($activeWorkspace->organization_department_id === null, fn ($query) => $query->where('workspace_id', $activeWorkspace->id))
                        ->where('requester_department_external_id', $organizationProfile['department_id'])
                        ->awaitingDepartment()
                        ->count();
            }
        }

        View::share([
            'activeWorkspace' => $activeWorkspace,
            'workspaceOptions' => $workspaces,
            'sidebarProjects' => $sidebarProjects,
            'sidebarMembers' => $sidebarMembers,
            'sidebarTaskMembers' => $sidebarTaskMembers,
            'sidebarTeamTaskMembers' => $sidebarTeamTaskMembers,
            'pendingApprovals' => $pendingApprovals,
            'organizationProfile' => $organizationProfile,
            'canApproveDepartmentRequests' => $canApproveDepartmentRequests,
            'departmentApprovalCount' => $departmentApprovalCount,
        ]);

        return $next($request);
    }
}
