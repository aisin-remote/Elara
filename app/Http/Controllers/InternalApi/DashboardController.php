<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\PerformanceRequest;
use App\Models\Workspace;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(PerformanceRequest $request, Workspace $workspace, DashboardService $dashboard): JsonResponse
    {
        return response()->json([
            'data' => $dashboard->forWorkspace($workspace, $request->user(), $request->validated()),
        ]);
    }
}
