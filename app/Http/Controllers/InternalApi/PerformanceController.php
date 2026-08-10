<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\PerformanceRequest;
use App\Models\Workspace;
use App\Services\PerformanceService;
use Illuminate\Http\JsonResponse;

class PerformanceController extends Controller
{
    public function index(PerformanceRequest $request, Workspace $workspace, PerformanceService $performance): JsonResponse
    {
        return response()->json([
            'data' => $performance->forWorkspace($workspace, $request->user(), $request->validated()),
        ]);
    }
}
