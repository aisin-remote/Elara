<?php

namespace App\Actions\Validation;

use App\Enums\CheckpointStatus;
use App\Enums\TaskStatusCategory;
use App\Models\ActivityLog;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\ValidationCheckpoint;
use App\Services\NotificationPreferenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The requester's answer. Either one stops the countdown — the ball is no longer theirs, and a
 * deadline that keeps running after someone has responded is a deadline that punishes them for
 * responding (PRD-07).
 */
class RespondToCheckpoint
{
    public function __construct(private readonly NotificationPreferenceService $notifications) {}

    public function handle(
        ValidationCheckpoint $checkpoint,
        User $actor,
        CheckpointStatus $decision,
        ?string $note = null,
    ): ValidationCheckpoint {
        if ($checkpoint->status !== CheckpointStatus::OPEN) {
            throw ValidationException::withMessages([
                'checkpoint' => 'This checkpoint has already been answered.',
            ]);
        }

        if ($decision === CheckpointStatus::CHANGES_REQUESTED && blank($note)) {
            throw ValidationException::withMessages([
                'response_note' => 'Say what needs changing, so the team knows what to do next.',
            ]);
        }

        DB::transaction(function () use ($checkpoint, $actor, $decision, $note): void {
            $checkpoint->update([
                'status' => $decision,
                'responded_at' => now(),
                'response_note' => $note,
            ]);

            if ($decision === CheckpointStatus::CHANGES_REQUESTED) {
                $this->reopenTask($checkpoint);
            }

            ActivityLog::record($checkpoint->workspace, $checkpoint->subject, 'validation_checkpoint.'.$decision->value, $actor, [
                'task' => $checkpoint->task->public_id,
                'note' => $note,
            ]);
        });

        $this->tellThePic($checkpoint->fresh(['task.assignees']), $actor, $decision, $note);

        return $checkpoint->fresh();
    }

    /** Back to the board it came from, in the first non-completed status of that project. */
    private function reopenTask(ValidationCheckpoint $checkpoint): void
    {
        $task = $checkpoint->task;

        $status = TaskStatus::query()
            ->active()
            ->where('project_id', $task->project_id)
            ->where('category', TaskStatusCategory::IN_PROGRESS->value)
            ->orderBy('position')
            ->first()
            ?? TaskStatus::query()
                ->active()
                ->where('project_id', $task->project_id)
                ->where('category', TaskStatusCategory::TODO->value)
                ->orderBy('position')
                ->first();

        $task->update([
            'completed_at' => null,
            'status_id' => $status?->id ?? $task->status_id,
            'status_changed_at' => now(),
        ]);
    }

    private function tellThePic(ValidationCheckpoint $checkpoint, User $actor, CheckpointStatus $decision, ?string $note): void
    {
        $title = $decision === CheckpointStatus::APPROVED
            ? 'A checkpoint was approved'
            : 'Changes requested';

        $body = $decision === CheckpointStatus::APPROVED
            ? $actor->name.' confirmed “'.$checkpoint->task->title.'”. Work continues.'
            : $actor->name.' asked for changes on “'.$checkpoint->task->title.'”: '.$note;

        foreach ($checkpoint->task->assignees as $assignee) {
            $this->notifications->notify(
                $assignee,
                $checkpoint->workspace,
                'validation_checkpoint',
                $title,
                $body,
                route('app.tasks.show', $checkpoint->task),
                ['checkpoint_public_id' => $checkpoint->public_id],
            );
        }
    }
}
