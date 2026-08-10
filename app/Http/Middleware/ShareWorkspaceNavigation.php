<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationRankGroup;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\ProjectRequest;
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

        $sidebarProjects = $activeWorkspace
            ? Project::query()
                ->delivery()
                ->visibleTo($request->user())
                ->where('workspace_id', $activeWorkspace->id)
                ->orderBy('name')
                ->limit(6)
                ->get()
            : collect();

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
            'organizationProfile' => $organizationProfile,
            'canApproveDepartmentRequests' => $canApproveDepartmentRequests,
            'departmentApprovalCount' => $departmentApprovalCount,
        ]);

        return $next($request);
    }
}
