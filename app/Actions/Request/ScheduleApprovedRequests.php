<?php

namespace App\Actions\Request;

use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectRequestStatus;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Models\Workspace;
use App\Services\CapacityPlanner;
use Illuminate\Support\Facades\DB;

/**
 * Drains the backlog: approved requests that carry an effort estimate, oldest approval
 * first, each given the earliest real slot the planner can find.
 *
 * Takedown (PRD-07) does not hand its capacity to a chosen successor — it simply releases
 * it, and this drain absorbs it. Two mechanisms that must agree would eventually disagree.
 */
class ScheduleApprovedRequests
{
    public function __construct(
        private readonly CapacityPlanner $planner,
        private readonly TransitionFeatureRequest $transitionFeature,
        private readonly TransitionProjectRequest $transitionProject,
    ) {}

    /** @return int Number of requests scheduled. */
    public function handle(Workspace $workspace): int
    {
        return $this->drainFeatures($workspace) + $this->drainProjects($workspace);
    }

    private function drainFeatures(Workspace $workspace): int
    {
        $scheduled = 0;

        $queue = FeatureRequest::where('workspace_id', $workspace->id)
            ->where('status', FeatureRequestStatus::APPROVED->value)
            ->whereNotNull('estimated_minutes')
            ->with(['system', 'assignee'])
            ->orderBy('reviewed_at')
            ->orderBy('id')
            ->get();

        foreach ($queue as $request) {
            if ($request->assignee) {
                $slot = $this->planner->availableFrom($workspace, $request->assignee, (int) $request->estimated_minutes);
                $assignment = $slot ? ['user' => $request->assignee, 'pic_deferred' => false, ...$slot] : null;
            } else {
                // Legacy approved rows may predate approval-time PIC selection.
                $assignment = $this->planner->assign($workspace, $request->system, (int) $request->estimated_minutes);
            }

            if ($assignment === null) {
                // No capacity inside the horizon: it stays approved and keeps its place
                // rather than being force-fitted into a date nobody can meet.
                continue;
            }

            DB::transaction(function () use ($request, $assignment, $workspace): void {
                $request->forceFill([
                    'assignee_id' => $assignment['user']->id,
                    'scheduled_start' => $assignment['start'],
                    'scheduled_due' => $assignment['due'],
                ])->save();

                ActivityLog::record($workspace, $request, 'feature_request.assigned', null, [
                    'assignee' => $assignment['user']->public_id,
                    'pic_deferred' => $assignment['pic_deferred'],
                ]);
            });

            $this->transitionFeature->handle($request->fresh(), FeatureRequestStatus::SCHEDULED, $assignment['user']);
            $scheduled++;
        }

        return $scheduled;
    }

    private function drainProjects(Workspace $workspace): int
    {
        $scheduled = 0;

        $queue = ProjectRequest::where('workspace_id', $workspace->id)
            ->where('status', ProjectRequestStatus::APPROVED->value)
            ->whereNotNull('estimated_minutes')
            ->orderBy('manager_at')
            ->orderBy('id')
            ->get();

        foreach ($queue as $request) {
            $assignment = $this->planner->assign($workspace, $request->project, (int) $request->estimated_minutes);

            if ($assignment === null) {
                continue;
            }

            DB::transaction(function () use ($request, $assignment, $workspace): void {
                $request->forceFill([
                    'assignee_id' => $assignment['user']->id,
                    'scheduled_start' => $assignment['start'],
                    'scheduled_due' => $assignment['due'],
                ])->save();

                $request->project?->update([
                    'start_date' => $assignment['start'],
                    'due_date' => $assignment['due'],
                ]);

                ActivityLog::record($workspace, $request, 'project_request.assigned', null, [
                    'assignee' => $assignment['user']->public_id,
                    'pic_deferred' => $assignment['pic_deferred'],
                ]);
            });

            $this->transitionProject->handle($request->fresh(), ProjectRequestStatus::SCHEDULED, $assignment['user']);
            $scheduled++;
        }

        return $scheduled;
    }

    /** Where an approved-but-unscheduled request sits in line, 1-indexed. */
    public function queuePosition(FeatureRequest|ProjectRequest $request): ?int
    {
        if ($request->status->value !== 'approved') {
            return null;
        }

        $queue = $request instanceof FeatureRequest
            ? FeatureRequest::where('workspace_id', $request->workspace_id)
                ->where('status', FeatureRequestStatus::APPROVED->value)
                ->orderBy('reviewed_at')->orderBy('id')->pluck('id')
            : ProjectRequest::where('workspace_id', $request->workspace_id)
                ->where('status', ProjectRequestStatus::APPROVED->value)
                ->orderBy('manager_at')->orderBy('id')->pluck('id');

        $index = $queue->search($request->id);

        return $index === false ? null : $index + 1;
    }
}
