<?php

namespace App\Console\Commands;

use App\Actions\Validation\TakeDownRequest;
use App\Enums\CheckpointStatus;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\ValidationCheckpoint;
use App\Services\NotificationPreferenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moves every open checkpoint one step closer to its deadline: a reminder at the midpoint, a
 * final warning a day out, and expiry after that.
 *
 * Hourly rather than per-minute: a deadline measured in days does not need minute precision,
 * and an hourly sweep is far easier to reason about when something has gone wrong at 2am.
 */
class SweepValidations extends Command
{
    protected $signature = 'orbitra:sweep-validations';

    protected $description = 'Remind, warn, and expire open validation checkpoints';

    public function handle(NotificationPreferenceService $notifications, TakeDownRequest $takedown): int
    {
        $expired = 0;
        $warned = 0;
        $reminded = 0;

        ValidationCheckpoint::query()
            ->open()
            // Questions ITD asked have no deadline and never take a request down.
            ->validations()
            ->with(['task.assignees', 'subject', 'requester', 'workspace'])
            ->orderBy('id')
            ->chunkById(100, function ($checkpoints) use ($notifications, $takedown, &$expired, &$warned, &$reminded): void {
                foreach ($checkpoints as $checkpoint) {
                    // Expiry first: a checkpoint past its deadline should not also collect a
                    // warning on the way out.
                    if (now()->greaterThanOrEqualTo($checkpoint->expires_at)) {
                        $this->expire($checkpoint, $notifications, $takedown);
                        $expired++;

                        continue;
                    }

                    if ($this->needsFinalWarning($checkpoint)) {
                        $this->finalWarning($checkpoint, $notifications);
                        $warned++;

                        continue;
                    }

                    if ($this->needsReminder($checkpoint)) {
                        $this->remind($checkpoint, $notifications);
                        $reminded++;
                    }
                }
            });

        $this->info("Reminded {$reminded}, warned {$warned}, expired {$expired}.");

        return self::SUCCESS;
    }

    /** Stamped before notifying, so a second run in the same hour sends nothing. */
    private function needsReminder(ValidationCheckpoint $checkpoint): bool
    {
        // A midpoint nudge arriving after the final warning reads as a step backwards. Once
        // the last day has been reached the reminder has missed its moment; it does not get
        // to fire late.
        if ($checkpoint->reminded_at !== null || $checkpoint->final_warning_at !== null) {
            return false;
        }

        if (now()->greaterThanOrEqualTo($checkpoint->expires_at->copy()->subDay())) {
            return false;
        }

        $midpoint = $checkpoint->opened_at->copy()->addSeconds(
            (int) round($checkpoint->opened_at->diffInSeconds($checkpoint->expires_at) / 2)
        );

        return now()->greaterThanOrEqualTo($midpoint);
    }

    private function needsFinalWarning(ValidationCheckpoint $checkpoint): bool
    {
        return $checkpoint->final_warning_at === null
            && now()->greaterThanOrEqualTo($checkpoint->expires_at->copy()->subDay());
    }

    private function remind(ValidationCheckpoint $checkpoint, NotificationPreferenceService $notifications): void
    {
        DB::transaction(fn () => $checkpoint->update(['reminded_at' => now()]));

        $notifications->notify(
            $checkpoint->requester,
            $checkpoint->workspace,
            'validation_checkpoint',
            'Still waiting for your confirmation',
            '“'.$checkpoint->task->title.'” needs your answer. '.$checkpoint->countdown().'.',
            route('desk.validations.index'),
            ['checkpoint_public_id' => $checkpoint->public_id],
        );
    }

    /**
     * The last warning copies the PIC and the supervisor deliberately: by now the work is one
     * day from being cancelled, and a human who can pick up a phone is a better failsafe than
     * an escalation policy.
     */
    private function finalWarning(ValidationCheckpoint $checkpoint, NotificationPreferenceService $notifications): void
    {
        DB::transaction(fn () => $checkpoint->update(['final_warning_at' => now()]));

        $body = 'Without an answer by '.$checkpoint->expires_at->format('j M').', “'
            .$checkpoint->subject?->title.'” is taken down and its place in the queue goes to another request.';

        foreach ($this->escalationRecipients($checkpoint) as $recipient) {
            $notifications->notify(
                $recipient,
                $checkpoint->workspace,
                'validation_checkpoint',
                'Last day to confirm',
                $body,
                $this->linkFor($checkpoint, $recipient),
                ['checkpoint_public_id' => $checkpoint->public_id],
            );
        }
    }

    private function expire(ValidationCheckpoint $checkpoint, NotificationPreferenceService $notifications, TakeDownRequest $takedown): void
    {
        DB::transaction(fn () => $checkpoint->update([
            'status' => CheckpointStatus::EXPIRED,
            'responded_at' => null,
        ]));

        $takedown->handle($checkpoint);

        $body = '“'.$checkpoint->subject?->title.'” was taken down: “'.$checkpoint->task->title
            .'” went unconfirmed past '.$checkpoint->expires_at->format('j M').'. The freed capacity goes to the next request in the queue.';

        foreach ($this->escalationRecipients($checkpoint) as $recipient) {
            $notifications->notify(
                $recipient,
                $checkpoint->workspace,
                'validation_checkpoint',
                'Request taken down',
                $body,
                $this->linkFor($checkpoint, $recipient),
                ['checkpoint_public_id' => $checkpoint->public_id],
            );
        }
    }

    /**
     * The requester's desk is closed to the delivery team and vice versa, so one shared URL
     * would send half the recipients of these notifications to a 403.
     */
    private function linkFor(ValidationCheckpoint $checkpoint, User $recipient): string
    {
        return $recipient->id === $checkpoint->requester_id
            ? route('desk.validations.index')
            : route('app.tasks.show', $checkpoint->task);
    }

    /** @return Collection<int, User> */
    private function escalationRecipients(ValidationCheckpoint $checkpoint)
    {
        $supervisors = $checkpoint->workspace->memberships()
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->whereIn('role', [WorkspaceRole::SUPERVISOR->value, WorkspaceRole::MANAGER->value])
            ->with('user')
            ->get()
            ->pluck('user');

        return collect([$checkpoint->requester])
            ->concat($checkpoint->task->assignees)
            ->concat($supervisors)
            ->filter()
            ->unique('id')
            ->values();
    }
}
