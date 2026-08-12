<?php

namespace Tests\Feature\Project;

use App\Actions\Project\CreateProject;
use App\Actions\Task\CreateTask;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProjectFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_project_with_owner_manager_membership(): void
    {
        [$owner, $workspace] = $this->workspace();

        $this->actingAs($owner)->postJson(route('internal.projects.store', $workspace), [
            'name' => 'Launch Orbitra',
            'description' => 'Phase one delivery',
            'color' => '#4f46e5',
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => '2026-07-29',
            'due_date' => '2026-08-29',
        ])->assertCreated()->assertJsonPath('data.name', 'Launch Orbitra');

        $project = Project::firstOrFail();
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'role' => ProjectMemberRole::MANAGER->value,
        ]);
        $this->assertSame(26, strlen($project->public_id));
    }

    public function test_project_update_detects_stale_version(): void
    {
        [$owner, , $project] = $this->project();
        $payload = [
            'name' => 'Updated project',
            'description' => null,
            'color' => '#334155',
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => null,
            'due_date' => null,
            'version' => 1,
        ];

        $this->actingAs($owner)->patchJson(route('internal.projects.update', $project), $payload)
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->actingAs($owner)->patchJson(route('internal.projects.update', $project), $payload)
            ->assertStatus(409)
            ->assertJsonPath('server_version', 2);
    }

    public function test_project_can_be_archived_and_restored(): void
    {
        [$owner, $workspace, $project] = $this->project();

        $this->actingAs($owner)->deleteJson(route('internal.projects.destroy', $project))->assertOk();
        $this->assertSoftDeleted('projects', ['id' => $project->id]);

        $archived = Project::withTrashed()->findOrFail($project->id);
        $this->actingAs($owner)->postJson(route('internal.projects.restore', $archived))->assertOk();
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'workspace_id' => $workspace->id,
            'deleted_at' => null,
            'status' => ProjectStatus::ACTIVE->value,
        ]);
    }

    public function test_project_access_respects_membership_and_role(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        foreach ([$viewer, $outsider] as $user) {
            $workspace->memberships()->create([
                'user_id' => $user->id,
                'role' => WorkspaceRole::VIEWER,
                'status' => WorkspaceMemberStatus::ACTIVE,
                'joined_at' => now(),
            ]);
        }
        $project->memberships()->create(['user_id' => $viewer->id, 'role' => ProjectMemberRole::VIEWER]);

        $this->actingAs($viewer)->get(route('app.projects.show', $project))->assertOk();
        $this->actingAs($viewer)->patchJson(route('internal.projects.update', $project), [
            'name' => 'Nope',
            'description' => null,
            'color' => null,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => null,
            'due_date' => null,
            'version' => 1,
        ])->assertForbidden();
        $this->actingAs($outsider)->get(route('app.projects.show', $project))->assertForbidden();
        $this->actingAs($owner)->get(route('app.projects.show', $project))->assertOk();
    }

    public function test_project_binding_hides_cross_workspace_and_numeric_ids(): void
    {
        [$owner, , $project] = $this->project();
        [, , $otherProject] = $this->project();

        $this->actingAs($owner)->get(route('app.projects.show', $otherProject))->assertNotFound();
        $this->actingAs($owner)->get('/app/projects/'.$project->id)->assertNotFound();
    }

    public function test_manager_can_add_update_and_remove_project_member(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $member = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $member->id,
            'role' => WorkspaceRole::MEMBER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)->postJson(route('internal.project-members.store', $project), [
            'member_public_id' => $member->public_id,
            'role' => ProjectMemberRole::MEMBER->value,
        ])->assertCreated();
        $this->actingAs($owner)->patchJson(route('internal.project-members.update', [$project, $member->public_id]), [
            'role' => ProjectMemberRole::VIEWER->value,
        ])->assertOk();
        $this->assertDatabaseHas('project_members', ['project_id' => $project->id, 'user_id' => $member->id, 'role' => 'viewer']);

        $this->actingAs($owner)->deleteJson(route('internal.project-members.destroy', [$project, $member->public_id]))->assertOk();
        $this->assertDatabaseMissing('project_members', ['project_id' => $project->id, 'user_id' => $member->id]);
    }

    public function test_project_overview_lists_leaders_before_members(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $member = User::factory()->create(['first_name' => 'Earlier', 'last_name' => 'Member']);
        $leader = User::factory()->create(['first_name' => 'Later', 'last_name' => 'Leader']);

        foreach ([$member, $leader] as $user) {
            $workspace->memberships()->create([
                'user_id' => $user->id,
                'role' => WorkspaceRole::MEMBER,
                'status' => WorkspaceMemberStatus::ACTIVE,
                'joined_at' => now(),
            ]);
        }

        $project->memberships()->create(['user_id' => $member->id, 'role' => ProjectMemberRole::MEMBER]);
        $project->memberships()->create(['user_id' => $leader->id, 'role' => ProjectMemberRole::MANAGER]);

        $this->actingAs($owner)->get(route('app.projects.show', $project))
            ->assertOk()
            ->assertSeeInOrder(['Later Leader', 'Earlier Member']);
    }

    public function test_project_list_create_and_edit_pages_render(): void
    {
        [$owner, $workspace, $project] = $this->project();

        $this->actingAs($owner)->get(route('app.projects.index', $workspace))->assertOk()->assertSee($project->name);
        $this->actingAs($owner)->get(route('app.projects.create', $workspace))
            ->assertOk()
            ->assertSee('Project name')
            // The left half lists what already exists, so a duplicate is visible before submitting.
            ->assertSee('Already in')
            ->assertSee($project->name);
        $this->actingAs($owner)->get(route('app.projects.edit', $project))->assertOk()->assertSee('Save project');
    }

    public function test_project_progress_uses_completed_non_cancelled_tasks(): void
    {
        [$owner, , $project] = $this->project();
        $todo = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $completed = $project->taskStatuses()->where('category', TaskStatusCategory::COMPLETED->value)->firstOrFail();
        foreach ([$todo, $completed] as $status) {
            app(CreateTask::class)->handle($project, $owner, [
                'title' => $status->name.' task', 'description' => null, 'status_public_id' => $status->public_id,
                'category_public_id' => null, 'priority' => TaskPriority::MEDIUM->value, 'start_at' => null,
                'due_at' => null, 'estimate_minutes' => null, 'assignee_public_ids' => [],
            ]);
        }

        $this->assertSame([
            'total' => 2,
            'completed' => 1,
            'percentage' => 50,
            'overdue' => 0,
            'buckets' => ['todo' => 1, 'in_progress' => 0, 'completed' => 1],
        ], $project->taskProgress());
    }

    public function test_project_progress_counts_overdue_open_tasks(): void
    {
        [$owner, , $project] = $this->project();
        $todo = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        foreach ([now()->subDay(), now()->addDay()] as $dueAt) {
            app(CreateTask::class)->handle($project, $owner, [
                'title' => 'Task due '.$dueAt->toDateString(), 'description' => null, 'status_public_id' => $todo->public_id,
                'category_public_id' => null, 'priority' => TaskPriority::MEDIUM->value, 'start_at' => null,
                'due_at' => $dueAt->toDateTimeString(), 'estimate_minutes' => null, 'assignee_public_ids' => [],
            ]);
        }

        $progress = $project->taskProgress();
        $this->assertSame(1, $progress['overdue']);
        $this->assertSame(2, $progress['buckets']['todo']);
    }

    public function test_schedule_health_flags_a_project_running_behind(): void
    {
        [, , $project] = $this->project();
        $this->travelTo(Carbon::parse('2026-07-30 12:00:00'));
        $project->forceFill(['start_date' => '2026-07-22', 'due_date' => '2026-08-01'])->save();

        $behind = $project->scheduleHealth(20);
        $this->assertSame(85, $behind['elapsed']);
        $this->assertSame(2, $behind['days_left']);
        $this->assertSame('behind', $behind['state']);

        $this->assertSame('on_track', $project->scheduleHealth(75)['state']);
        $this->assertSame('complete', $project->scheduleHealth(100)['state']);
        $this->assertSame('overdue', $project->forceFill(['due_date' => '2026-07-28'])->scheduleHealth(60)['state']);
        $this->assertNull($project->forceFill(['due_date' => null])->scheduleHealth(50));
    }

    public function test_timeline_renders_dated_tasks_as_bars_and_lists_undated_ones(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $this->travelTo(Carbon::parse('2026-07-30 09:00:00'));
        $todo = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        foreach ([['Draft the API contract', '2026-07-31 09:00:00'], ['Someday idea', null]] as [$title, $dueAt]) {
            app(CreateTask::class)->handle($project, $owner, [
                'title' => $title, 'description' => null, 'status_public_id' => $todo->public_id,
                'category_public_id' => null, 'priority' => TaskPriority::MEDIUM->value, 'start_at' => null,
                'due_at' => $dueAt, 'estimate_minutes' => null, 'assignee_public_ids' => [],
            ]);
        }

        $this->actingAs($owner)->get(route('app.projects.timeline', [$workspace, $project]))
            ->assertOk()
            ->assertSee('Task schedule')
            ->assertSee('Draft the API contract')
            ->assertSee('Not scheduled')
            ->assertSee('Someday idea');
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => fake()->company(),
            'timezone' => 'UTC',
            'locale' => 'en',
            'week_start' => 1,
        ]);

        return [$owner, $workspace];
    }

    private function project(): array
    {
        [$owner, $workspace] = $this->workspace();
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'color' => '#4f46e5',
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => null,
            'due_date' => null,
        ]);

        return [$owner, $workspace, $project];
    }
}
