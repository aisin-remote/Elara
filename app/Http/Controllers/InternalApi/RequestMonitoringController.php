<?php

namespace App\Http\Controllers\InternalApi;

use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Services\RequestProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestMonitoringController extends Controller
{
    public function feature(Request $request, FeatureRequest $featureRequest, RequestProgressService $progress): JsonResponse
    {
        $this->authorize('view', $featureRequest);

        return response()->json($progress->build($featureRequest, $request->user()));
    }

    public function project(Request $request, ProjectRequest $projectRequest, RequestProgressService $progress): JsonResponse
    {
        $this->authorize('view', $projectRequest);

        return response()->json($progress->build($projectRequest, $request->user()));
    }
}
