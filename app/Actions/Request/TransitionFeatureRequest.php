<?php

namespace App\Actions\Request;

use App\Enums\FeatureRequestStatus;
use App\Jobs\GenerateTaskBreakdown;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one place a feature request changes state. Controllers ask for a transition; this
 * decides whether it is legal, records who did it and why, and notifies the people who
 * need to know. Keeping it here means a new entry point cannot invent a shortcut.
 */
class TransitionFeatureRequest
{
    public function __construct(private readonly NotifyRequestParticipants $notifier) {}

    public function handle(
        FeatureRequest $request,
        FeatureRequestStatus $next,
        ?User $actor,
        ?string $note = null,
    ): FeatureRequest {
        $current = $request->status;

        if (! $current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => "A request that is {$current->label()} cannot become {$next->label()}.",
            ]);
        }

        if ($current === FeatureRequestStatus::NEEDS_INFO) {
            $expected = $request->needs_info_stage === 'department'
                ? FeatureRequestStatus::PENDING_DEPARTMENT
                : FeatureRequestStatus::PENDING_REVIEW;

            if ($next !== $expected && $next !== FeatureRequestStatus::REJECTED) {
                throw ValidationException::withMessages([
                    'status' => 'The request must return to the approval stage that asked for more information.',
                ]);
            }
        }

        // Rejection and needs-info exist to tell the requester something. Without a note
        // they tell them nothing, so the transition itself refuses.
        if (in_array($next, [FeatureRequestStatus::REJECTED, FeatureRequestStatus::NEEDS_INFO], true) && blank($note)) {
            throw ValidationException::withMessages([
                'decision_note' => 'Say why, so the requester knows what to do next.',
            ]);
        }

        $result = DB::transaction(function () use ($request, $current, $next, $actor, $note): FeatureRequest {
            $isDepartmentDecision = $current === FeatureRequestStatus::PENDING_DEPARTMENT
                && in_array($next, [FeatureRequestStatus::PENDING_REVIEW, FeatureRequestStatus::REJECTED, FeatureRequestStatus::NEEDS_INFO], true);
            $isItDecision = $current === FeatureRequestStatus::PENDING_REVIEW
                && in_array($next, [
                    FeatureRequestStatus::APPROVED,
                    FeatureRequestStatus::REJECTED,
                    FeatureRequestStatus::NEEDS_INFO,
                ], true);

            $attributes = [
                'status' => $next,
                'needs_info_stage' => $next === FeatureRequestStatus::NEEDS_INFO
                    ? ($isDepartmentDecision ? 'department' : 'it')
                    : null,
                'version' => $request->version + 1,
            ];

            if ($isDepartmentDecision) {
                $attributes += [
                    'department_reviewed_by' => $actor?->id,
                    'department_reviewed_at' => now(),
                    'department_decision_note' => $note,
                ];
            }

            if ($isItDecision) {
                $attributes += [
                    'decision_note' => $note,
                    'reviewed_by' => $actor?->id,
                    'reviewed_at' => now(),
                ];
            } elseif ($current === FeatureRequestStatus::NEEDS_INFO && $request->needs_info_stage === 'it') {
                $attributes['decision_note'] = null;
            }

            $request->forceFill($attributes)->save();

            ActivityLog::record($request->workspace, $request, 'feature_request.'.$next->value, $actor, [
                'from' => $current->value,
                'note' => $note,
            ]);

            $this->notifier->handle($request->fresh(['requester', 'system']), $current, $actor);

            return $request;
        });

        // Dispatched after the commit, not inside it: on the database queue a worker can pick
        // the job up before an open transaction is visible to it.
        if ($next === FeatureRequestStatus::APPROVED) {
            GenerateTaskBreakdown::dispatch($result);
        }

        return $result;
    }
}
