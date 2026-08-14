<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\PerformanceRequest;
use App\Models\Workspace;
use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardWidgetController extends Controller
{
    public function insights(PerformanceRequest $request, Workspace $workspace, DashboardService $dashboard): View
    {
        $this->authorize('view', $workspace);

        return view('app.dashboard-widgets.insights', [
            'workspace' => $workspace,
            'dashboard' => $dashboard->insights($workspace, $request->user(), $request->validated()),
        ]);
    }
}
