<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Planning\CaptureProjectBaseline;
use App\Models\Project;
use App\Services\Planning\DateShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectPlanningController extends Controller
{
    public function baseline(Request $request, Project $project, CaptureProjectBaseline $capture): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $project);
        $count = $capture->handle($project, $request->user(), $request->ip());

        return $this->success(
            $request,
            ['tasks' => $count],
            'Baseline captured for '.$count.' task'.($count === 1 ? '' : 's').'.',
            route('app.projects.timeline', [$project->workspace, $project]),
        );
    }

    public function reschedule(Request $request, Project $project, DateShiftService $shift): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $project);
        $result = $shift->shiftProject($project, $request->user(), $request->ip());

        return $this->success(
            $request,
            $result,
            $result['shifted'] === 0
                ? 'Dates already respect dependencies.'
                : 'Rescheduled '.$result['shifted'].' task'.($result['shifted'] === 1 ? '' : 's').'.',
            route('app.projects.timeline', [$project->workspace, $project]),
        );
    }
}
