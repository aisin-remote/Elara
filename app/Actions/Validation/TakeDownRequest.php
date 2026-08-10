<?php

namespace App\Actions\Validation;

use App\Actions\Request\TransitionFeatureRequest;
use App\Actions\Request\TransitionProjectRequest;
use App\Enums\CheckpointStatus;
use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectRequestStatus;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\Task;
use App\Models\ValidationCheckpoint;
use Illuminate\Support\Facades\DB;

/**
 * Silence takes the work down (PRD-07). Archived, not deleted: an admin can still read it,
 * and the requester's route back is a new request rather than a resurrection.
 *
 * Capacity is released, not handed over. `orbitra:drain-request-queue` absorbs it. A direct
 * hand-off from the cancelled item to a chosen successor would be a second mechanism that has
 * to agree with the first, and eventually would not.
 */
class TakeDownRequest
{
    public function __construct(
        private readonly TransitionFeatureRequest $transitionFeature,
        private readonly TransitionProjectRequest $transitionProject,
    ) {}

    public function handle(ValidationCheckpoint $checkpoint): void
    {
        $subject = $checkpoint->subject;

        if ($subject === null) {
            return;
        }

        DB::transaction(function () use ($checkpoint, $subject): void {
            $container = $subject instanceof FeatureRequest ? $subject->feature : $subject->project;

            // Unfinished work first: archiving these is what frees the assignee's committed
            // effort, which is the whole point of a takedown.
            $archived = 0;

            if ($container) {
                $query = $subject instanceof FeatureRequest
                    ? Task::where('feature_id', $container->id)
                    : Task::where('project_id', $container->id);

                $archived = $query->whereNull('archived_at')->whereNull('completed_at')->update(['archived_at' => now()]);
                $container->update(['archived_at' => now()]);
            }

            // Any other checkpoint on this subject is moot now; leaving them open would keep
            // asking the requester about work that no longer exists.
            ValidationCheckpoint::where('subject_type', $checkpoint->subject_type)
                ->where('subject_id', $checkpoint->subject_id)
                ->where('id', '!=', $checkpoint->id)
                ->open()
                ->update(['status' => CheckpointStatus::CANCELLED, 'responded_at' => now()]);

            ActivityLog::record($checkpoint->workspace, $subject, 'request.taken_down', null, [
                'checkpoint' => $checkpoint->public_id,
                'task' => $checkpoint->task->title,
                'expired_at' => $checkpoint->expires_at->toIso8601String(),
                'tasks_archived' => $archived,
            ]);
        });

        // Outside the transaction: the transition notifies, and a notification sent from
        // inside a transaction that later rolls back is a message about something that
        // never happened.
        $subject instanceof FeatureRequest
            ? $this->transitionFeature->handle($subject->fresh(), FeatureRequestStatus::TAKEN_DOWN, null)
            : $this->transitionProject->handle($subject->fresh(), ProjectRequestStatus::TAKEN_DOWN, null);
    }
}
