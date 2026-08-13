<?php

namespace Tests\Feature\Ai;

use App\Actions\Project\CreateProject;
use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\BreakdownStatus;
use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectStatus;
use App\Enums\RequestUrgency;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Feature;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskBreakdown;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CapacityPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AcceptBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // Monday, so the working-week arithmetic below reads plainly.
        $this->travelTo(CarbonImmutable::parse('2026-08-03 09:00:00', 'UTC'));
    }

    public function test_accepting_creates_a_feature_with_its_tasks_on_the_board(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $breakdown = $this->readyBreakdown($workspace, $system, $pic);

        $this->actingAs($pic)
            ->post(route('internal.breakdowns.accept', $breakdown), ['tasks' => $this->payload()])
            ->assertRedirect();

        $feature = Feature::firstOrFail();
        $this->assertSame('Export the monthly stock report', $feature->name);
        $this->assertSame($system->id, $feature->project_id);
        $this->assertSame(3, $feature->tasks()->count());

        $task = $feature->tasks()->orderBy('position')->first();
        $this->assertSame($system->id, $task->project_id);
        $this->assertSame(180, $task->estimate_minutes);
        $this->assertTrue($task->assignees()->where('users.id', $pic->id)->exists());

        $breakdown->refresh();
        $this->assertSame(BreakdownStatus::ACCEPTED, $breakdown->status);
        $this->assertSame($pic->id, $breakdown->accepted_by);
        $this->assertDatabaseHas('activity_logs', ['action' => 'task_breakdown.accepted']);
    }

    public function test_the_reviewers_edited_estimate_wins_over_the_model(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $breakdown = $this->readyBreakdown($workspace, $system, $pic);

        $edited = $this->payload();
        $edited[0]['estimate_minutes'] = 600;

        $this->actingAs($pic)
            ->post(route('internal.breakdowns.accept', $breakdown), ['tasks' => $edited])
            ->assertRedirect();

        $this->assertSame(600, Task::orderBy('position')->first()->estimate_minutes);
        // 600 + 120 + 60, written back so the planner works from what was accepted.
        $this->assertSame(780, $breakdown->subject->fresh()->estimated_minutes);
    }

    public function test_accepting_creates_checklists_without_task_dependencies(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $breakdown = $this->readyBreakdown($workspace, $system, $pic);

        $this->actingAs($pic)
            ->post(route('internal.breakdowns.accept', $breakdown), ['tasks' => $this->payload()])
            ->assertRedirect();

        $tasks = Task::orderBy('position')->get();
        $this->assertSame(['Validate request parameters', 'Return the export file'], $tasks[0]->checklistItems()->pluck('title')->all());
        $this->assertSame(0, $tasks[1]->dependencies()->count());
        $this->assertSame(0, $tasks[2]->dependencies()->count());

        $tasks[0]->checklistItems()->first()->update(['is_completed' => true, 'completed_at' => now()]);

        $this->actingAs($pic)
            ->get(route('app.projects.timeline', [$workspace, $system]))
            ->assertOk()
            ->assertSee('50 percent complete');
    }

    public function test_task_detail_hides_dependency_and_time_tracking_controls(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $breakdown = $this->readyBreakdown($workspace, $system, $pic);

        $this->actingAs($pic)
            ->post(route('internal.breakdowns.accept', $breakdown), ['tasks' => $this->payload()])
            ->assertRedirect();

        $this->actingAs($pic)
            ->get(route('app.tasks.show', Task::firstOrFail()))
            ->assertOk()
            ->assertDontSee('Dependencies')
            ->assertDontSee('Time tracking')
            ->assertDontSee('Log time');
    }

    public function test_tasks_are_laid_out_across_working_days_not_stacked_on_one(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $breakdown = $this->readyBreakdown($workspace, $system, $pic);

        // 360 + 360 + 360 at six hours a day: three consecutive working days.
        $tasks = array_map(fn (array $task) => [...$task, 'estimate_minutes' => 360], $this->payload());

        $this->actingAs($pic)->post(route('internal.breakdowns.accept', $breakdown), ['tasks' => $tasks]);

        $dues = Task::orderBy('position')->pluck('due_at')->map(fn ($date) => $date->format('Y-m-d'))->all();
        $this->assertSame(['2026-08-03', '2026-08-04', '2026-08-05'], $dues);
    }

    public function test_accepting_releases_the_capacity_reservation_rather_than_double_counting(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $breakdown = $this->readyBreakdown($workspace, $system, $pic);
        $request = $breakdown->subject;

        // Before acceptance the request reserves its whole window: 30 hours, Mon–Fri.
        $request->forceFill(['estimated_minutes' => 30 * 60, 'scheduled_due' => '2026-08-07'])->save();
        $before = app(CapacityPlanner::class)->availableFrom($workspace, $pic, 60);
        $this->assertSame('2026-08-10', $before['start']->format('Y-m-d'));

        // Accepting six hours of real tasks replaces that reservation with the tasks.
        $this->actingAs($pic)->post(route('internal.breakdowns.accept', $breakdown), [
            'tasks' => [['title' => 'Only task', 'description' => 'x', 'estimate_minutes' => 360, 'requires_user_validation' => 0]],
        ])->assertRedirect();

        $after = app(CapacityPlanner::class)->availableFrom($workspace, $pic, 60);
        $this->assertSame('2026-08-04', $after['start']->format('Y-m-d'), 'Monday is spent by the accepted task; Tuesday is free.');
    }

    public function test_accepting_twice_does_not_create_a_second_set_of_tasks(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $breakdown = $this->readyBreakdown($workspace, $system, $pic);

        $this->actingAs($pic)->post(route('internal.breakdowns.accept', $breakdown), ['tasks' => $this->payload()]);
        $this->actingAs($pic)
            ->post(route('internal.breakdowns.accept', $breakdown), ['tasks' => $this->payload()])
            ->assertSessionHasErrors('breakdown');

        $this->assertSame(3, Task::count());
        $this->assertSame(1, Feature::count());
    }

    public function test_a_requester_cannot_accept_a_plan(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $breakdown = $this->readyBreakdown($workspace, $system, $pic);
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        $this->actingAs($requester)
            ->post(route('internal.breakdowns.accept', $breakdown), ['tasks' => $this->payload()])
            ->assertForbidden();

        $this->assertSame(0, Task::count());
    }

    public function test_an_empty_or_zero_estimate_plan_is_refused(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $breakdown = $this->readyBreakdown($workspace, $system, $pic);

        $this->actingAs($pic)
            ->post(route('internal.breakdowns.accept', $breakdown), ['tasks' => []])
            ->assertSessionHasErrors('tasks');

        $this->actingAs($pic)
            ->post(route('internal.breakdowns.accept', $breakdown), [
                'tasks' => [['title' => 'Nothing', 'description' => 'x', 'estimate_minutes' => 0, 'requires_user_validation' => 0]],
            ])
            ->assertSessionHasErrors('tasks.0.estimate_minutes');

        $this->assertSame(0, Task::count());
    }

    public function test_discarding_keeps_the_draft_auditable_and_writes_no_tasks(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $breakdown = $this->readyBreakdown($workspace, $system, $pic);

        $this->actingAs($pic)->post(route('internal.breakdowns.discard', $breakdown))->assertRedirect();

        $this->assertSame(BreakdownStatus::DISCARDED, $breakdown->fresh()->status);
        $this->assertNotNull($breakdown->fresh()->payload_json, 'A discarded draft is still readable.');
        $this->assertSame(0, Task::count());
    }

    public function test_the_preview_answers_with_a_finish_date_from_the_planner(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $breakdown = $this->readyBreakdown($workspace, $system, $pic);

        $this->actingAs($pic)
            ->postJson(route('internal.breakdowns.preview', $breakdown), ['minutes' => [360, 360]])
            ->assertOk()
            ->assertJson(['total_minutes' => 720, 'finish' => '2026-08-04']);
    }

    public function test_an_it_created_project_can_accept_an_ai_plan(): void
    {
        [$workspace, $owner] = $this->system();
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Internal automation',
            'description' => 'Automate a recurring IT operations workflow.',
            'color' => '#4f46e5',
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => '2026-08-03',
            'due_date' => null,
        ]);
        $breakdown = TaskBreakdown::create([
            'workspace_id' => $workspace->id,
            'subject_type' => $project->getMorphClass(),
            'subject_id' => $project->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'status' => BreakdownStatus::READY,
            'payload_json' => ['tasks' => [[
                'title' => 'Build the automation',
                'description' => 'Implement and verify it.',
                'estimate_minutes' => 120,
                'checklist' => ['Implement the workflow', 'Verify the result'],
                'requires_user_validation' => true,
                'validation_reason' => 'This should be ignored for direct IT work.',
            ]]],
            'generated_at' => now(),
        ]);

        $this->actingAs($owner)->post(route('internal.breakdowns.accept', $breakdown), [
            'tasks' => $breakdown->tasks(),
        ])->assertRedirect();

        $task = Task::where('project_id', $project->id)->firstOrFail();
        $this->assertFalse($task->requires_user_validation);
        $this->assertNull($task->validation_reason);
        $this->assertSame(['Implement the workflow', 'Verify the result'], $task->checklistItems()->pluck('title')->all());
    }

    public function test_a_breakdown_in_another_workspace_is_not_reachable(): void
    {
        [, $pic] = $this->system();
        [$otherWorkspace, $otherPic, $otherSystem] = $this->system();
        $breakdown = $this->readyBreakdown($otherWorkspace, $otherSystem, $otherPic);

        $this->actingAs($pic)
            ->post(route('internal.breakdowns.accept', $breakdown), ['tasks' => $this->payload()])
            ->assertNotFound();
    }

    /** @return array<int, array<string, mixed>> */
    private function payload(): array
    {
        return [
            [
                'title' => 'Add the export endpoint', 'description' => 'Server side.', 'estimate_minutes' => 180,
                'checklist' => ['Validate request parameters', 'Return the export file'],
                'requires_user_validation' => 0,
            ],
            [
                'title' => 'Add the download button', 'description' => 'Client side.', 'estimate_minutes' => 120,
                'checklist' => ['Render the button', 'Handle the download response'],
                'requires_user_validation' => 1, 'validation_reason' => 'The requester confirms the columns.',
            ],
            [
                'title' => 'Write the smoke test', 'description' => 'One run.', 'estimate_minutes' => 60,
                'checklist' => ['Cover a successful export', 'Cover an invalid request'],
                'requires_user_validation' => 0,
            ],
        ];
    }

    private function system(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Inventory Core', 'description' => 'Stock levels.', 'color' => '#8b5cf6', 'pic_id' => $owner->id,
        ]);

        return [$workspace, $owner, $system];
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

    private function readyBreakdown(Workspace $workspace, Project $system, User $assignee): TaskBreakdown
    {
        $request = FeatureRequest::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'requester_id' => $this->member($workspace, WorkspaceRole::REQUESTER)->id,
            'title' => 'Export the monthly stock report',
            'problem' => 'We copy the numbers by hand every month and it takes two days.',
            'desired_outcome' => 'A download button producing the columns finance already uses.',
            'benefit' => 'Saves about two staff days each month and reduces transcription errors.',
            'urgency' => RequestUrgency::NORMAL,
            'status' => FeatureRequestStatus::SCHEDULED,
            'estimated_minutes' => 360,
        ]);

        $request->forceFill([
            'assignee_id' => $assignee->id,
            'scheduled_start' => '2026-08-03',
            'scheduled_due' => '2026-08-03',
        ])->save();

        return TaskBreakdown::create([
            'workspace_id' => $workspace->id,
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'status' => BreakdownStatus::READY,
            'payload_json' => ['tasks' => $this->payload()],
            'generated_at' => now(),
        ]);
    }
}
