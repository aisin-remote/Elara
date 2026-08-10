<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Requests\Project\StoreProjectMilestoneRequest;
use App\Http\Requests\Project\UpdateProjectMilestoneRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectMilestoneController extends Controller
{
    public function store(StoreProjectMilestoneRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        $milestone = $project->milestones()->create([
            'workspace_id' => $project->workspace_id,
            ...$request->validated(),
        ]);
        ActivityLog::record($project->workspace, $milestone, 'milestone.created', $request->user(), ipAddress: $request->ip());

        return $this->success($request, $this->data($milestone), 'Milestone created.', route('app.projects.timeline', [$project->workspace, $project]), 201);
    }

    public function update(UpdateProjectMilestoneRequest $request, ProjectMilestone $milestone): JsonResponse|RedirectResponse
    {
        $milestone->update([
            'name' => $request->string('name')->toString(),
            'target_date' => $request->date('target_date'),
            'completed_at' => $request->boolean('completed') ? ($milestone->completed_at ?? now()) : null,
        ]);
        ActivityLog::record($milestone->workspace, $milestone, 'milestone.updated', $request->user(), ipAddress: $request->ip());

        return $this->success($request, $this->data($milestone), 'Milestone updated.', route('app.projects.timeline', [$milestone->workspace, $milestone->project]));
    }

    public function destroy(Request $request, ProjectMilestone $milestone): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $milestone->project);
        $redirect = route('app.projects.timeline', [$milestone->workspace, $milestone->project]);
        DB::transaction(function () use ($milestone, $request): void {
            ActivityLog::record($milestone->workspace, $milestone, 'milestone.deleted', $request->user(), ipAddress: $request->ip());
            $milestone->tasks()->update(['milestone_id' => null]);
            $milestone->delete();
        });

        return $this->success($request, null, 'Milestone removed.', $redirect);
    }

    private function data(ProjectMilestone $milestone): array
    {
        return [
            'public_id' => $milestone->public_id,
            'name' => $milestone->name,
            'target_date' => $milestone->target_date->toDateString(),
            'completed_at' => $milestone->completed_at,
        ];
    }
}
