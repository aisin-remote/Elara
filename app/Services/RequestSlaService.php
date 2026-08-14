<?php

namespace App\Services;

use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use Carbon\CarbonInterface;

class RequestSlaService
{
    public function for(FeatureRequest|ProjectRequest $request): ?array
    {
        $project = $request instanceof ProjectRequest;
        $hours = config(($project ? 'request_sla.project_hours.' : 'request_sla.feature_hours.').$request->status->value);

        if (! $hours) {
            return null;
        }

        $startedAt = $this->startedAt($request);
        $dueAt = $startedAt->copy()->addHours($hours);
        $elapsedMinutes = max(0, $startedAt->diffInMinutes(now()));
        $ratio = $elapsedMinutes / ($hours * 60);
        $state = match (true) {
            now()->greaterThan($dueAt) => 'breached',
            $ratio >= config('request_sla.warning_at', 0.75) => 'warning',
            default => 'on_track',
        };

        return [
            'state' => $state,
            'label' => match ($state) {
                'breached' => 'Overdue '.$dueAt->diffForHumans(now(), true),
                default => 'Due '.$dueAt->diffForHumans(),
            },
            'tone' => match ($state) {
                'breached' => 'danger',
                'warning' => 'warning',
                default => 'success',
            },
            'age' => $startedAt->diffForHumans(syntax: true),
            'due_at' => $dueAt,
            'owner' => $this->owner($request),
        ];
    }

    private function startedAt(FeatureRequest|ProjectRequest $request): CarbonInterface
    {
        $status = $request->status->value;

        return match ($status) {
            'pending_review' => $request->department_reviewed_at ?? $request->created_at,
            'pending_meeting' => $request->department_reviewed_at ?? $request->created_at,
            'pending_spv' => $request->meeting_held_at ?? $request->updated_at,
            'pending_manager' => $request->spv_at ?? $request->updated_at,
            'approved' => $request instanceof ProjectRequest
                ? ($request->manager_at ?? $request->updated_at)
                : ($request->reviewed_at ?? $request->updated_at),
            'scheduled' => $request->scheduled_start ?? $request->updated_at,
            default => $request->created_at,
        };
    }

    private function owner(FeatureRequest|ProjectRequest $request): string
    {
        return match ($request->status->value) {
            'pending_department' => 'Department manager / coordinator',
            'pending_review', 'pending_spv' => 'ITD supervisor',
            'pending_manager' => 'ITD manager',
            'pending_meeting' => 'ITD scoping team',
            'approved', 'scheduled' => 'ITD planner',
            default => 'ITD team',
        };
    }
}
