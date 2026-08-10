<?php

namespace App\Http\Controllers\InternalApi;

use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Services\RequestProgressService;
use Illuminate\Http\JsonResponse;

class RequestMonitoringController extends Controller
{
    public function feature(FeatureRequest $featureRequest, RequestProgressService $progress): JsonResponse
    {
        $this->authorize('view', $featureRequest);

        return response()->json($progress->build($featureRequest));
    }

    public function project(ProjectRequest $projectRequest, RequestProgressService $progress): JsonResponse
    {
        $this->authorize('view', $projectRequest);

        return response()->json($progress->build($projectRequest));
    }
}
