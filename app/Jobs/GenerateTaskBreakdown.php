<?php

namespace App\Jobs;

use App\Contracts\TaskBreakdownGenerator;
use App\Enums\BreakdownStatus;
use App\Exceptions\TaskBreakdownFailed;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Models\TaskBreakdown;
use App\Services\NotificationPreferenceService;
use App\Services\WorkspaceSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Asks the provider for a proposed task list and stores it for review. Never writes tasks:
 * that needs an explicit human acceptance (PRD-06).
 *
 * A failure here must not block delivery. The request stays approved, the reason is visible,
 * and manual entry stays available.
 */
class GenerateTaskBreakdown implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Declared with a default rather than promoted into the constructor on purpose. A payload
     * already sitting in the queue was serialized by an older version of this class and has no
     * `note` key; a promoted property carries no default, so restoring that payload left this
     * uninitialized and the job died on first read. A declared default survives
     * newInstanceWithoutConstructor, which is how a queued job is rebuilt.
     */
    private ?string $note = null;

    public function __construct(
        private readonly FeatureRequest|ProjectRequest $request,
        ?string $note = null,
    ) {
        $this->note = $note;
    }

    public function handle(TaskBreakdownGenerator $generator): void
    {
        // Idempotent by design: a draft already waiting for review, or already accepted, is
        // not replaced. Regenerating is a deliberate act in the review screen, not a side
        // effect of the job running twice.
        $settled = TaskBreakdown::forSubject($this->request)
            ->whereIn('status', [BreakdownStatus::READY->value, BreakdownStatus::ACCEPTED->value])
            ->exists();

        if ($settled) {
            return;
        }

        // A failed row is reused rather than left behind: retrying is dispatching this job
        // again, and a pile of failed rows for one request tells the reviewer nothing.
        $breakdown = TaskBreakdown::forSubject($this->request)
            ->whereIn('status', [BreakdownStatus::PENDING->value, BreakdownStatus::FAILED->value])
            ->orderByDesc('id')
            ->first()
            ?? TaskBreakdown::create([
                'workspace_id' => $this->request->workspace_id,
                'subject_type' => $this->request->getMorphClass(),
                'subject_id' => $this->request->getKey(),
                'provider' => 'openai',
                'model' => app(WorkspaceSettings::class)->aiModel($this->request->workspace),
                'status' => BreakdownStatus::PENDING,
            ]);

        try {
            $result = $generator->generate($this->request, $this->note);
        } catch (TaskBreakdownFailed $e) {
            $breakdown->update([
                'status' => BreakdownStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);

            ActivityLog::record($this->request->workspace, $this->request, 'task_breakdown.failed', null, [
                'reason' => $e->getMessage(),
            ]);

            return;
        }

        $breakdown->update([
            'status' => BreakdownStatus::READY,
            'provider' => $result['provider'],
            'model' => $result['model'],
            'payload_json' => ['tasks' => $result['tasks']],
            'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
            'error_message' => null,
            'generated_at' => now(),
        ]);

        ActivityLog::record($this->request->workspace, $this->request, 'task_breakdown.ready', null, [
            'model' => $result['model'],
            'tasks' => count($result['tasks']),
        ]);

        $this->tellSomeone($breakdown->fresh());
    }

    /**
     * A plan nobody is told about is a plan nobody accepts, and the work sits still while the
     * queue behind it waits. The PIC is the one being scheduled, so they hear about it first.
     */
    private function tellSomeone(TaskBreakdown $breakdown): void
    {
        $recipient = $this->request->assignee;

        if (! $recipient) {
            return;
        }

        $count = count($breakdown->tasks());

        app(NotificationPreferenceService::class)->notify(
            $recipient,
            $this->request->workspace,
            'task_breakdown',
            'A plan is ready for your review',
            "{$count} proposed tasks for “{$this->request->title}”, ".round($breakdown->totalMinutes() / 60, 1).' hours in total. Nothing reaches your board until you accept it.',
            route('app.approvals.index', $this->request->workspace),
            ['breakdown_public_id' => $breakdown->public_id],
        );
    }
}
