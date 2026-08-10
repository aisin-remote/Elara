<?php

namespace App\Actions\Request;

use App\Actions\Project\CreateProject;
use App\Enums\ProjectRequestStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Jobs\GenerateTaskBreakdown;
use App\Models\ActivityLog;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one place a project request changes state. It owns three rules the UI must never be
 * the only guard for: the meeting gate, the two distinct signatures, and creating exactly
 * one project on approval.
 */
class TransitionProjectRequest
{
    public function __construct(
        private readonly NotifyProjectRequestParticipants $notifier,
        private readonly CreateProject $createProject,
    ) {}

    public function handle(
        ProjectRequest $request,
        ProjectRequestStatus $next,
        ?User $actor,
        ?string $note = null,
    ): ProjectRequest {
        $current = $request->status;

        if (! $current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => "A request that is \"{$current->label()}\" cannot become \"{$next->label()}\".",
            ]);
        }

        if ($current === ProjectRequestStatus::NEEDS_INFO) {
            $expected = $request->needs_info_stage === 'department'
                ? ProjectRequestStatus::PENDING_DEPARTMENT
                : ProjectRequestStatus::PENDING_SPV;

            if ($next !== $expected && $next !== ProjectRequestStatus::REJECTED) {
                throw ValidationException::withMessages([
                    'status' => 'The request must return to the approval stage that asked for more information.',
                ]);
            }
        }

        if ($next === ProjectRequestStatus::PENDING_SPV && $current === ProjectRequestStatus::PENDING_MEETING && ! $request->meetingHeld()) {
            throw ValidationException::withMessages([
                'meeting' => 'Hold the scoping meeting and record what came out of it first.',
            ]);
        }

        if (in_array($next, [ProjectRequestStatus::REJECTED, ProjectRequestStatus::NEEDS_INFO], true) && blank($note)) {
            throw ValidationException::withMessages([
                'note' => 'Say why, so the requester knows what to do next.',
            ]);
        }

        // Two signatures means two people. One person holding both roles is still one person.
        if ($next === ProjectRequestStatus::APPROVED && $request->spv_id === $actor?->id) {
            throw ValidationException::withMessages([
                'note' => 'The second signature must come from someone other than the supervisor who gave the first.',
            ]);
        }

        $result = DB::transaction(function () use ($request, $current, $next, $actor, $note): ProjectRequest {
            $isDepartmentDecision = $current === ProjectRequestStatus::PENDING_DEPARTMENT
                && in_array($next, [ProjectRequestStatus::PENDING_MEETING, ProjectRequestStatus::REJECTED, ProjectRequestStatus::NEEDS_INFO], true);
            $attributes = [
                'status' => $next,
                'needs_info_stage' => $next === ProjectRequestStatus::NEEDS_INFO
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

            if ($next === ProjectRequestStatus::PENDING_MANAGER) {
                $attributes += ['spv_id' => $actor->id, 'spv_at' => now(), 'spv_note' => $note];
            }

            if ($next === ProjectRequestStatus::APPROVED) {
                $attributes += ['manager_id' => $actor->id, 'manager_at' => now(), 'manager_note' => $note];
                $attributes['project_id'] = $this->createDeliveryProject($request, $actor)->id;
            }

            if (($next === ProjectRequestStatus::REJECTED || $next === ProjectRequestStatus::NEEDS_INFO) && ! $isDepartmentDecision) {
                $column = $current === ProjectRequestStatus::PENDING_MANAGER ? 'manager_note' : 'spv_note';
                $attributes += [$column => $note];
                $attributes += $current === ProjectRequestStatus::PENDING_MANAGER
                    ? ['manager_id' => $actor->id, 'manager_at' => now()]
                    : ['spv_id' => $actor->id, 'spv_at' => now()];
            }

            $request->forceFill($attributes)->save();

            ActivityLog::record($request->workspace, $request, 'project_request.'.$next->value, $actor, [
                'from' => $current->value,
                'note' => $note,
            ]);

            $this->notifier->handle($request->fresh(['requester']), $current, $actor);

            return $request;
        });

        // After the commit: the delivery project is created inside that transaction, and the
        // generator reads it for context.
        if ($next === ProjectRequestStatus::APPROVED) {
            GenerateTaskBreakdown::dispatch($result);
        }

        return $result;
    }

    /**
     * The approved request becomes an ordinary project. The requester is deliberately not
     * added as a member: they follow progress from their own desk, not the board.
     */
    private function createDeliveryProject(ProjectRequest $request, User $actor)
    {
        return $this->createProject->handle($request->workspace, $actor, [
            'name' => $request->title,
            'type' => ProjectType::PROJECT,
            'description' => $request->concept,
            'color' => '#2eb0fb',
            'status' => ProjectStatus::PLANNED,
            'due_date' => $request->target_date,
        ]);
    }
}
