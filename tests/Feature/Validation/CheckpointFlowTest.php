<?php

namespace Tests\Feature\Validation;

use App\Actions\Project\CreateSystem;
use App\Actions\Task\MoveTask;
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
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\ValidationCheckpoint;
use App\Models\Workspace;
use App\Notifications\OrbitraNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckpointFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-08-03 09:00:00', 'UTC'));
    }

    public function test_completing_a_flagged_task_opens_a_checkpoint_and_tells_the_requester(): void
    {
        [$workspace, $pic, , $task, $requester] = $this->scenario();

        $this->complete($task, $pic);

        $checkpoint = ValidationCheckpoint::firstOrFail();
        $this->assertSame(CheckpointStatus::OPEN, $checkpoint->status);
        $this->assertSame($requester->id, $checkpoint->requester_id);
        $this->assertSame($task->id, $checkpoint->task_id);
        $this->assertSame('The requester must confirm the columns.', $checkpoint->reason);
        // Seven days is the workspace default, stamped at opening time.
        $this->assertSame('2026-08-10', $checkpoint->expires_at->format('Y-m-d'));

        Notification::assertSentTo($requester, OrbitraNotification::class);
        $this->assertDatabaseHas('activity_logs', ['action' => 'validation_checkpoint.opened']);
    }

    public function test_a_task_without_the_flag_opens_nothing(): void
    {
        [$workspace, $pic, , $task] = $this->scenario();
        $task->update(['requires_user_validation' => false]);

        $this->complete($task, $pic);

        $this->assertSame(0, ValidationCheckpoint::count());
    }

    public function test_the_window_in_force_at_opening_is_the_one_that_counts(): void
    {
        [$workspace, $pic, , $task] = $this->scenario();
        $workspace->update(['settings_json' => ['validation_window_days' => 3]]);

        $this->complete($task, $pic);
        $checkpoint = ValidationCheckpoint::firstOrFail();
        $this->assertSame('2026-08-06', $checkpoint->expires_at->format('Y-m-d'));

        // Changing the window afterwards must not move a countdown already running.
        $workspace->update(['settings_json' => ['validation_window_days' => 30]]);
        $this->assertSame('2026-08-06', $checkpoint->fresh()->expires_at->format('Y-m-d'));
    }

    public function test_completing_the_same_task_twice_does_not_stack_checkpoints(): void
    {
        [$workspace, $pic, $project, $task] = $this->scenario();

        $this->complete($task, $pic);
        $this->moveTo($task->fresh(), $pic, TaskStatusCategory::TODO);
        $this->complete($task->fresh(), $pic);

        $this->assertSame(1, ValidationCheckpoint::count());
    }

    public function test_the_requester_approves_and_the_pic_hears_about_it(): void
    {
        [$workspace, $pic, , $task, $requester] = $this->scenario();
        $this->complete($task, $pic);
        $checkpoint = ValidationCheckpoint::firstOrFail();

        $this->actingAs($requester)
            ->post(route('desk.validations.respond', $checkpoint), ['decision' => 'approved'])
            ->assertRedirect(route('desk.validations.index'));

        $checkpoint->refresh();
        $this->assertSame(CheckpointStatus::APPROVED, $checkpoint->status);
        $this->assertNotNull($checkpoint->responded_at);
        // Approving leaves the schedule alone: the task stays done.
        $this->assertNotNull($task->fresh()->completed_at);
        Notification::assertSentTo($pic, OrbitraNotification::class);
    }

    public function test_requesting_changes_stops_the_countdown_and_reopens_the_task(): void
    {
        [$workspace, $pic, , $task, $requester] = $this->scenario();
        $this->complete($task, $pic);
        $checkpoint = ValidationCheckpoint::firstOrFail();

        $this->actingAs($requester)
            ->post(route('desk.validations.respond', $checkpoint), [
                'decision' => 'changes_requested',
                'response_note' => 'The totals column is missing.',
            ])
            ->assertRedirect();

        $checkpoint->refresh();
        $this->assertSame(CheckpointStatus::CHANGES_REQUESTED, $checkpoint->status);
        $this->assertFalse($checkpoint->status->isCountingDown(), 'The requester answered; the deadline is no longer theirs.');
        $this->assertSame('The totals column is missing.', $checkpoint->response_note);
        $this->assertNull($task->fresh()->completed_at, 'The work goes back to the PIC.');
    }

    public function test_requesting_changes_without_a_note_is_refused(): void
    {
        [$workspace, $pic, , $task, $requester] = $this->scenario();
        $this->complete($task, $pic);
        $checkpoint = ValidationCheckpoint::firstOrFail();

        $this->actingAs($requester)
            ->post(route('desk.validations.respond', $checkpoint), ['decision' => 'changes_requested'])
            ->assertSessionHasErrors('response_note');

        $this->assertSame(CheckpointStatus::OPEN, $checkpoint->fresh()->status);
    }

    public function test_nobody_but_the_requester_can_answer(): void
    {
        [$workspace, $pic, , $task] = $this->scenario();
        $this->complete($task, $pic);
        $checkpoint = ValidationCheckpoint::firstOrFail();

        // The PIC confirming their own work is the loophole the checkpoint exists to close.
        $this->actingAs($pic)
            ->post(route('desk.validations.respond', $checkpoint), ['decision' => 'approved'])
            ->assertForbidden();

        $this->assertSame(CheckpointStatus::OPEN, $checkpoint->fresh()->status);
    }

    public function test_answering_twice_is_refused(): void
    {
        [$workspace, $pic, , $task, $requester] = $this->scenario();
        $this->complete($task, $pic);
        $checkpoint = ValidationCheckpoint::firstOrFail();

        $this->actingAs($requester)->post(route('desk.validations.respond', $checkpoint), ['decision' => 'approved']);
        $this->actingAs($requester)
            ->post(route('desk.validations.respond', $checkpoint), ['decision' => 'approved'])
            ->assertForbidden();
    }

    public function test_the_desk_page_states_the_deadline_and_its_consequence(): void
    {
        [$workspace, $pic, , $task, $requester] = $this->scenario();
        $this->complete($task, $pic);

        $this->actingAs($requester)
            ->get(route('desk.validations.index'))
            ->assertOk()
            ->assertSee('7 days left')
            ->assertSee('August 10, 2026')
            ->assertSee('is cancelled and ITD moves to the next request');
    }

    /** @return array{0: Workspace, 1: User, 2: Project, 3: Task, 4: User} */
    private function scenario(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Inventory Core', 'description' => null, 'color' => '#8b5cf6', 'pic_id' => $owner->id,
        ]);

        $requester = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $requester->id, 'role' => WorkspaceRole::REQUESTER,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);

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

        $status = $system->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        $task = Task::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'feature_id' => $feature->id,
            'status_id' => $status->id,
            'creator_id' => $owner->id,
            'title' => 'Add the download button',
            'description' => 'Client side.',
            'priority' => TaskPriority::MEDIUM,
            'estimate_minutes' => 120,
            'requires_user_validation' => true,
            'validation_reason' => 'The requester must confirm the columns.',
            'position' => 1024,
        ]);
        $task->assignees()->attach($owner->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);

        return [$workspace, $owner, $system, $task, $requester];
    }

    private function complete(Task $task, User $actor): void
    {
        $this->moveTo($task, $actor, TaskStatusCategory::COMPLETED);
    }

    /** Through MoveTask, the board path — the one a PIC actually uses. */
    private function moveTo(Task $task, User $actor, TaskStatusCategory $category): void
    {
        // Refreshed first: version comes from a database default, so a freshly created model
        // has it as null and the optimistic-lock check would silently reject the move.
        $task = $task->fresh();
        $status = $task->project->taskStatuses()->where('category', $category->value)->firstOrFail();

        app(MoveTask::class)->handle($task, $actor, [
            'status_public_id' => $status->public_id,
            'operation_id' => (string) Str::ulid(),
            'version' => $task->version,
        ]);
    }
}
