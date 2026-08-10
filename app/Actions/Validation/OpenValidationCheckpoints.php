<?php

namespace App\Actions\Validation;

use App\Enums\CheckpointStatus;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Models\Task;
use App\Models\ValidationCheckpoint;
use App\Services\NotificationPreferenceService;
use App\Services\WorkspaceSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A task that produces something only the requester can judge pauses there until they say so
 * (PRD-07). Called from every path that can complete a task — there is more than one, and a
 * checkpoint that opens from only one of them is worse than none, because the rule would look
 * enforced while quietly depending on which button someone pressed.
 */
class OpenValidationCheckpoints
{
    public function __construct(
        private readonly WorkspaceSettings $settings,
        private readonly NotificationPreferenceService $notifications,
    ) {}

    public function handle(Task $task): ?ValidationCheckpoint
    {
        if (! $task->requires_user_validation || $task->completed_at === null || $task->archived_at !== null) {
            return null;
        }

        $subject = $this->subject($task);

        if ($subject === null) {
            // Ordinary board work carries no requester to ask. The flag only means something
            // for a task a request produced.
            return null;
        }

        $checkpoint = DB::transaction(function () use ($task, $subject): ?ValidationCheckpoint {
            // Completing, reopening, and completing again must not stack up checkpoints.
            $existing = ValidationCheckpoint::where('task_id', $task->id)
                ->whereIn('status', [CheckpointStatus::OPEN->value, CheckpointStatus::APPROVED->value])
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                return null;
            }

            $opened = now();

            return ValidationCheckpoint::create([
                'workspace_id' => $task->workspace_id,
                'task_id' => $task->id,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'requester_id' => $subject->requester_id,
                'reason' => $task->validation_reason,
                'status' => CheckpointStatus::OPEN,
                'opened_at' => $opened,
                // Stamped from the window in force right now, then never recomputed.
                'expires_at' => $opened->copy()->addDays($this->settings->validationWindowDays($task->workspace)),
            ]);
        });

        if ($checkpoint === null) {
            return null;
        }

        ActivityLog::record($task->workspace, $subject, 'validation_checkpoint.opened', null, [
            'task' => $task->public_id,
            'expires_at' => $checkpoint->expires_at->toIso8601String(),
        ]);

        $this->notifications->notify(
            $checkpoint->requester,
            $task->workspace,
            'validation_checkpoint',
            'Something needs your confirmation',
            '“'.$task->title.'” is ready for you to check. Without an answer by '
                .$checkpoint->expires_at->format('j M').', the request is taken down.',
            route('desk.validations.index'),
            ['checkpoint_public_id' => $checkpoint->public_id],
        );

        return $checkpoint;
    }

    /** The request a task belongs to, through its feature or its project. */
    private function subject(Task $task): ?Model
    {
        if ($task->feature_id) {
            $request = FeatureRequest::where('feature_id', $task->feature_id)->first();

            if ($request) {
                return $request;
            }
        }

        return ProjectRequest::where('project_id', $task->project_id)->first();
    }
}
