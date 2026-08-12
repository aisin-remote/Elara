<?php

namespace Tests\Feature\Task;

use App\Actions\Project\CreateProject;
use App\Actions\Task\CreateTask;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dependency_drives_blocked_state_and_clears_when_the_prerequisite_finishes(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $prerequisite = $this->task($project, $owner, 'Approve API contract', '2026-08-06 17:00:00');
        $dependent = $this->task($project, $owner, 'Build API', '2026-08-08 17:00:00');

        $this->actingAs($owner)->postJson(route('internal.task-dependencies.store', $dependent), [
            'dependency_public_id' => $prerequisite->public_id,
        ])->assertCreated()->assertJsonPath('data.is_blocked', true);

        $this->assertTrue($dependent->fresh()->isBlocked());
        $this->assertDatabaseHas('task_dependencies', ['task_id' => $dependent->id, 'depends_on_task_id' => $prerequisite->id]);

        $this->actingAs($owner)->get(route('app.tasks.index', ['workspace' => $workspace, 'tab' => 'blocked']))
            ->assertOk()->assertSee('Build API')->assertDontSee('Approve API contract');
        $this->actingAs($owner)->get(route('app.projects.tasks', ['workspace' => $workspace, 'project' => $project, 'blocked' => 1]))
            ->assertOk()->assertSee('Build API')->assertDontSee('Approve API contract');

        $prerequisite->update(['completed_at' => now()]);

        $this->assertFalse($dependent->fresh()->isBlocked());
    }

    public function test_dependency_rejects_self_foreign_workspace_and_circular_chains(): void
    {
        [$owner, , $project] = $this->project();
        $first = $this->task($project, $owner, 'First');
        $second = $this->task($project, $owner, 'Second');
        $siblingProject = app(CreateProject::class)->handle($project->workspace, $owner, [
            'name' => 'Sibling project', 'description' => null, 'color' => '#f43f5e',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => null, 'due_date' => null,
        ]);
        $sibling = $this->task($siblingProject, $owner, 'Sibling task');

        $this->actingAs($owner)->postJson(route('internal.task-dependencies.store', $first), [
            'dependency_public_id' => $first->public_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('dependency_public_id');

        $this->actingAs($owner)->postJson(route('internal.task-dependencies.store', $first), [
            'dependency_public_id' => $sibling->public_id,
        ])->assertCreated();

        $this->actingAs($owner)->postJson(route('internal.task-dependencies.store', $first), [
            'dependency_public_id' => $second->public_id,
        ])->assertCreated();
        $this->actingAs($owner)->postJson(route('internal.task-dependencies.store', $second), [
            'dependency_public_id' => $first->public_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('dependency_public_id');

        $this->assertDatabaseCount('task_dependencies', 2);
    }

    public function test_list_and_timeline_explain_blocked_work_and_draw_the_dependency(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $prerequisite = $this->task($project, $owner, 'Approve design', '2026-08-06 17:00:00', '2026-08-05 09:00:00');
        $dependent = $this->task($project, $owner, 'Build interface', '2026-08-09 17:00:00', '2026-08-07 09:00:00');
        $dependent->dependencies()->attach($prerequisite->id);

        $this->actingAs($owner)->get(route('app.projects.tasks', [$workspace, $project]))
            ->assertOk()
            ->assertSee('Blocked');

        $this->actingAs($owner)->get(route('app.projects.timeline', [$workspace, $project]))
            ->assertOk()
            ->assertSee('data-dependency-lines', false)
            ->assertSee('waits for Approve design');
    }

    public function test_project_leader_can_manage_milestones_and_assign_tasks_to_them(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $leader = $this->addProjectMember($project, ProjectMemberRole::MANAGER, WorkspaceRole::MEMBER);
        $task = $this->task($project, $owner, 'Release candidate');

        $response = $this->actingAs($leader)->postJson(route('internal.project-milestones.store', $project), [
            'name' => 'MVP ready',
            'target_date' => '2026-08-12',
        ])->assertCreated()->assertJsonPath('data.name', 'MVP ready');
        $milestone = ProjectMilestone::where('public_id', $response->json('data.public_id'))->firstOrFail();

        $this->actingAs($leader)->patchJson(route('internal.tasks.update', $task), [
            ...$this->payload($task->status->public_id, 'Release candidate'),
            'milestone_public_id' => $milestone->public_id,
            'version' => $task->fresh()->version,
        ])->assertOk()->assertJsonPath('data.milestone.name', 'MVP ready');

        $this->actingAs($leader)->get(route('app.projects.timeline', [$workspace, $project]))
            ->assertOk()->assertSee('MVP ready')->assertSee('Zero-duration targets');

        $this->actingAs($leader)->patchJson(route('internal.project-milestones.update', $milestone), [
            'name' => 'MVP launched',
            'target_date' => '2026-08-13',
            'completed' => true,
        ])->assertOk()->assertJsonPath('data.name', 'MVP launched');
        $this->assertNotNull($milestone->fresh()->completed_at);

        $this->actingAs($leader)->deleteJson(route('internal.project-milestones.destroy', $milestone))->assertOk();
        $this->assertNull($task->fresh()->milestone_id);
    }

    public function test_viewer_cannot_manage_dependencies_or_milestones(): void
    {
        [$owner, , $project] = $this->project();
        $first = $this->task($project, $owner, 'First');
        $second = $this->task($project, $owner, 'Second');
        $viewer = $this->addProjectMember($project, ProjectMemberRole::VIEWER, WorkspaceRole::VIEWER);

        $this->actingAs($viewer)->postJson(route('internal.task-dependencies.store', $first), [
            'dependency_public_id' => $second->public_id,
        ])->assertForbidden();
        $this->actingAs($viewer)->postJson(route('internal.project-milestones.store', $project), [
            'name' => 'Forbidden milestone', 'target_date' => '2026-08-12',
        ])->assertForbidden();
    }

    private function project(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Website Redesign', 'description' => null, 'color' => '#4f46e5',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => '2026-08-01', 'due_date' => '2026-08-31',
        ]);

        return [$owner, $workspace, $project];
    }

    private function task($project, User $creator, string $title, ?string $dueAt = null, ?string $startAt = null): Task
    {
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $payload = $this->payload($status->public_id, $title, $dueAt, $startAt);
        $payload['assignee_public_ids'] = [$creator->public_id];

        return app(CreateTask::class)->handle($project, $creator, $payload);
    }

    private function payload(string $statusPublicId, string $title = 'Planning task', ?string $dueAt = null, ?string $startAt = null): array
    {
        return [
            'title' => $title,
            'description' => 'Phase 16A planning foundation coverage.',
            'status_public_id' => $statusPublicId,
            'category_public_id' => null,
            'milestone_public_id' => null,
            'priority' => TaskPriority::HIGH->value,
            'start_at' => $startAt,
            'due_at' => $dueAt,
            'estimate_minutes' => 240,
            'assignee_public_ids' => [],
        ];
    }

    private function addProjectMember($project, ProjectMemberRole $projectRole, WorkspaceRole $workspaceRole): User
    {
        $user = User::factory()->create();
        $project->workspace->memberships()->create([
            'user_id' => $user->id,
            'role' => $workspaceRole,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        $project->memberships()->create(['user_id' => $user->id, 'role' => $projectRole]);

        return $user;
    }
}
