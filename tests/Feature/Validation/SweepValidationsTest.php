<?php

namespace Tests\Feature\Validation;

use App\Actions\Project\CreateSystem;
use App\Actions\Request\ScheduleApprovedRequests;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\CheckpointStatus;
use App\Enums\FeatureRequestStatus;
use App\Enums\RequestUrgency;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Feature;
use App\Models\FeatureRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\ValidationCheckpoint;
use App\Models\Workspace;
use App\Notifications\OrbitraNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SweepValidationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-08-03 09:00:00', 'UTC'));
    }

    public function test_nothing_happens_before_the_midpoint(): void
    {
        [, , $checkpoint] = $this->openCheckpoint();

        $this->travelTo(CarbonImmutable::parse('2026-08-05 09:00:00', 'UTC'));
        $this->artisan('orbitra:sweep-validations')->assertSuccessful();

        $checkpoint->refresh();
        $this->assertNull($checkpoint->reminded_at);
        $this->assertNull($checkpoint->final_warning_at);
        $this->assertSame(CheckpointStatus::OPEN, $checkpoint->status);
    }

    public function test_the_midpoint_reminds_once_however_often_the_sweeper_runs(): void
    {
        [, $requester, $checkpoint] = $this->openCheckpoint();

        // Opened 3 Aug, expires 10 Aug: the midpoint is 6 Aug.
        $this->travelTo(CarbonImmutable::parse('2026-08-06 21:00:00', 'UTC'));
        $this->artisan('orbitra:sweep-validations');
        $this->artisan('orbitra:sweep-validations');

        $checkpoint->refresh();
        $this->assertNotNull($checkpoint->reminded_at);
        $this->assertSame(CheckpointStatus::OPEN, $checkpoint->status);
        Notification::assertSentToTimes($requester, OrbitraNotification::class, 1);
    }

    public function test_the_final_warning_copies_the_pic_and_the_supervisor(): void
    {
        [$workspace, $requester, $checkpoint, $pic] = $this->openCheckpoint();
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);

        $this->travelTo(CarbonImmutable::parse('2026-08-09 12:00:00', 'UTC'));
        $this->artisan('orbitra:sweep-validations');
        $this->artisan('orbitra:sweep-validations');

        $checkpoint->refresh();
        $this->assertNotNull($checkpoint->final_warning_at);
        $this->assertSame(CheckpointStatus::OPEN, $checkpoint->status, 'A warning is not an expiry.');

        // One day out, a human who can pick up a phone beats an escalation policy.
        Notification::assertSentToTimes($requester, OrbitraNotification::class, 1);
        Notification::assertSentToTimes($pic, OrbitraNotification::class, 1);
        Notification::assertSentToTimes($supervisor, OrbitraNotification::class, 1);
    }

    public function test_expiry_takes_the_request_down_and_archives_its_unfinished_work(): void
    {
        [$workspace, , $checkpoint, $pic, $request, $feature, $task] = $this->openCheckpoint();
        $unfinished = $this->extraTask($feature, $pic, 'Write the smoke test');

        $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00:00', 'UTC'));
        $this->artisan('orbitra:sweep-validations');

        $this->assertSame(CheckpointStatus::EXPIRED, $checkpoint->fresh()->status);
        $this->assertSame(FeatureRequestStatus::TAKEN_DOWN, $request->fresh()->status);
        $this->assertNotNull($feature->fresh()->archived_at, 'Archived, not destroyed.');
        $this->assertNotNull($unfinished->fresh()->archived_at);
        // The completed one keeps its record: takedown removes future work, not history.
        $this->assertNull($task->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'request.taken_down']);
    }

    public function test_expiry_frees_capacity_and_the_queue_absorbs_it(): void
    {
        [$workspace, , $checkpoint, $pic, $request, $feature] = $this->openCheckpoint();
        // Effort lands on a task's due date, so this fills 10 August specifically — the first
        // day the queued request would otherwise take.
        $this->extraTask($feature, $pic, 'Heavy build', 30 * 60, '2026-08-10');

        $queued = $this->approvedRequest($workspace, $request->system, 6 * 60);

        $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00:00', 'UTC'));

        $this->assertSame(1, app(ScheduleApprovedRequests::class)->handle($workspace));
        $this->assertSame('2026-08-11', $queued->fresh()->scheduled_start->format('Y-m-d'),
            '10 August is spoken for, so the queue has to step over it.');

        // Put it back in the queue. refresh() first: save() writes only dirty attributes, and
        // a stale instance still believes it is approved, so nothing would be written at all.
        $queued->refresh();
        $queued->forceFill([
            'status' => FeatureRequestStatus::APPROVED,
            'assignee_id' => null, 'scheduled_start' => null, 'scheduled_due' => null,
        ])->save();

        $this->artisan('orbitra:sweep-validations');
        app(ScheduleApprovedRequests::class)->handle($workspace);

        $this->assertSame('2026-08-10', $queued->fresh()->scheduled_start->format('Y-m-d'),
            'The takedown archived the work holding 10 August, and the queue absorbed it.');
    }

    public function test_an_answered_checkpoint_is_never_expired_by_the_sweeper(): void
    {
        [, $requester, $checkpoint] = $this->openCheckpoint();

        $this->actingAs($requester)->post(route('desk.validations.respond', $checkpoint), [
            'decision' => 'changes_requested',
            'response_note' => 'The totals column is missing.',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-08-20 10:00:00', 'UTC'));
        $this->artisan('orbitra:sweep-validations');

        $this->assertSame(CheckpointStatus::CHANGES_REQUESTED, $checkpoint->fresh()->status);
        $this->assertSame(FeatureRequestStatus::IN_PROGRESS, $checkpoint->subject->fresh()->status);
    }

    public function test_a_takedown_cancels_the_other_open_checkpoints_on_the_same_request(): void
    {
        [$workspace, $requester, $checkpoint, $pic, $request, $feature] = $this->openCheckpoint();

        $second = ValidationCheckpoint::create([
            'workspace_id' => $workspace->id,
            'task_id' => $this->extraTask($feature, $pic, 'Second deliverable')->id,
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
            'requester_id' => $requester->id,
            'status' => CheckpointStatus::OPEN,
            'opened_at' => now(),
            'expires_at' => now()->addDays(20),
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00:00', 'UTC'));
        $this->artisan('orbitra:sweep-validations');

        $this->assertSame(CheckpointStatus::CANCELLED, $second->fresh()->status,
            'Leaving it open would keep asking about work that no longer exists.');
    }

    /** @return array{0: Workspace, 1: User, 2: ValidationCheckpoint, 3: User, 4: FeatureRequest, 5: Feature, 6: Task} */
    private function openCheckpoint(): array
    {
        $pic = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($pic, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        $system = app(CreateSystem::class)->handle($workspace, $pic, [
            'name' => 'Inventory Core', 'description' => null, 'color' => '#8b5cf6', 'pic_id' => $pic->id,
        ]);
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        $feature = Feature::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'name' => 'Export the monthly stock report',
        ]);

        $request = FeatureRequest::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'requester_id' => $requester->id,
            'title' => 'Export the monthly stock report',
            'problem' => 'We copy the numbers by hand every month and it takes two days.',
            'desired_outcome' => 'A download button producing the columns finance already uses.',
            'benefit' => 'Saves about two staff days each month and reduces transcription errors.',
            'urgency' => RequestUrgency::NORMAL,
            'status' => FeatureRequestStatus::IN_PROGRESS,
            'feature_id' => $feature->id,
        ]);
        $request->forceFill(['assignee_id' => $pic->id])->save();

        $task = $this->extraTask($feature, $pic, 'Add the download button');
        $task->update(['completed_at' => now()]);

        $checkpoint = ValidationCheckpoint::create([
            'workspace_id' => $workspace->id,
            'task_id' => $task->id,
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
            'requester_id' => $requester->id,
            'reason' => 'The requester must confirm the columns.',
            'status' => CheckpointStatus::OPEN,
            'opened_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        return [$workspace, $requester, $checkpoint, $pic, $request, $feature, $task];
    }

    private function extraTask(Feature $feature, User $pic, string $title, int $minutes = 120, ?string $dueOn = null): Task
    {
        $status = $feature->project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        $task = Task::create([
            'workspace_id' => $feature->workspace_id,
            'project_id' => $feature->project_id,
            'feature_id' => $feature->id,
            'status_id' => $status->id,
            'creator_id' => $pic->id,
            'title' => $title,
            'priority' => TaskPriority::MEDIUM,
            'estimate_minutes' => $minutes,
            'due_at' => ($dueOn ?? '2026-08-05').' 17:00:00',
            'position' => 1024,
        ]);
        $task->assignees()->attach($pic->id, ['assigned_by' => $pic->id, 'assigned_at' => now()]);

        return $task;
    }

    private function approvedRequest(Workspace $workspace, $system, int $minutes): FeatureRequest
    {
        return FeatureRequest::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'requester_id' => $this->member($workspace, WorkspaceRole::REQUESTER)->id,
            'title' => 'Bulk edit reorder levels',
            'problem' => 'Reorder levels are set one product at a time and there are eleven hundred.',
            'desired_outcome' => 'Select many products and set the level once.',
            'benefit' => 'Saves about two staff days each month and reduces transcription errors.',
            'urgency' => RequestUrgency::NORMAL,
            'status' => FeatureRequestStatus::APPROVED,
            'reviewed_at' => now()->subDay(),
            'estimated_minutes' => $minutes,
        ]);
    }

    private function member(Workspace $workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $user->id, 'role' => $role,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);

        return $user;
    }
}
