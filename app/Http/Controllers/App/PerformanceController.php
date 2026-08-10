<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\PerformanceRequest;
use App\Models\TaskStatus;
use App\Models\Workspace;
use App\Services\PerformanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function index(PerformanceRequest $request, Workspace $workspace, PerformanceService $performance): View
    {
        $filters = $request->validated();
        $projects = $workspace->projects()->visibleTo($request->user())->orderBy('name')->get();
        $statuses = TaskStatus::query()->active()
            ->whereHas('project', fn (Builder $project) => $project
                ->visibleTo($request->user())->where('workspace_id', $workspace->id))
            ->with('project:id,name')->orderBy('name')->get();

        return view('app.performance.index', [
            'workspace' => $workspace,
            'report' => $performance->forWorkspace($workspace, $request->user(), $filters),
            'projects' => $projects,
            'members' => $workspace->memberships()->active()->with('user')->get(),
            'statuses' => $statuses,
            'filters' => $filters,
        ]);
    }
}
