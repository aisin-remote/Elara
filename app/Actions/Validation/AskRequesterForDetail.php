<?php

namespace App\Actions\Validation;

use App\Enums\CheckpointStatus;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\ValidationCheckpoint;
use App\Services\NotificationPreferenceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * ITD asking the requester for something a task needs: a sample file, a missing rule, a
 * decision only they can make. It lands in the same "Waiting on me" queue as the automatic
 * checkpoint, because to the requester both are the same thing — ITD is blocked on them.
 *
 * Unlike a validation checkpoint it carries no deadline: nobody's request should be cancelled
 * for being slow to answer a question ITD chose to ask.
 */
class AskRequesterForDetail
{
    public function __construct(private readonly NotificationPreferenceService $notifications) {}

    public function handle(Task $task, User $asker, string $question): ValidationCheckpoint
    {
        $subject = $this->subject($task);

        if ($subject === null) {
            throw ValidationException::withMessages([
                'question' => 'This task did not come from a request, so there is no requester to ask.',
            ]);
        }

        // One open question at a time per task: a second one buries the first, and the
        // requester cannot tell which answer ITD is still waiting for.
        $pending = ValidationCheckpoint::where('task_id', $task->id)
            ->where('kind', ValidationCheckpoint::KIND_INFORMATION)
            ->where('status', CheckpointStatus::OPEN->value)
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'question' => 'This task already has a question waiting with the requester.',
            ]);
        }

        $checkpoint = ValidationCheckpoint::create([
            'workspace_id' => $task->workspace_id,
            'task_id' => $task->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'kind' => ValidationCheckpoint::KIND_INFORMATION,
            'requester_id' => $subject->requester_id,
            'opened_by' => $asker->id,
            'reason' => $question,
            'status' => CheckpointStatus::OPEN,
            'opened_at' => now(),
            'expires_at' => null,
        ]);

        ActivityLog::record($task->workspace, $subject, 'validation_checkpoint.asked', $asker, [
            'task' => $task->public_id,
            'question' => $question,
        ]);

        $this->notifications->notify(
            $checkpoint->requester,
            $task->workspace,
            'validation_checkpoint',
            'ITD needs something from you',
            $asker->name.' asked about “'.$task->title.'”: '.$question,
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
