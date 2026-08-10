<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\PerformanceRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $workspace = $request->user()->workspaceMemberships()->active()->with('workspace')->first()?->workspace;

        return $workspace
            ? redirect()->route('app.workspaces.show', $workspace)
            : redirect()->route('app.workspaces.create');
    }

    public function show(PerformanceRequest $request, Workspace $workspace, DashboardService $dashboard): View
    {
        $request->session()->put('active_workspace_id', $workspace->id);
        $filters = $request->validated();
        $projects = $workspace->projects()->delivery()->visibleTo($request->user())->orderBy('name')->get();

        return view('app.dashboard', [
            'workspace' => $workspace,
            'dashboard' => $dashboard->forWorkspace($workspace, $request->user(), $filters),
            'projects' => $projects,
            'creatableProjects' => $projects->filter(fn (Project $project) => $request->user()->can('create', [Task::class, $project])),
            'canCreateProject' => $request->user()->can('create', [Project::class, $workspace]),
        ]);
    }
}
