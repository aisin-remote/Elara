<?php

namespace Tests\Feature\Task;

use App\Actions\Project\CreateProject;
use App\Actions\Task\CreateTask;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\TaskPriority;
use App\Enums\TaskPropertyType;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskProperty;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalTaskDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_tasks_provisions_one_private_customizable_database(): void
    {
        [$owner, $workspace] = $this->workspace();

        $this->actingAs($owner)->get(route('app.tasks.index', $workspace))
            ->assertOk()
            ->assertSee('My task database')
            ->assertSee('Add task')
            ->assertSee('Add property')
            ->assertSee('Group by: Workflow status');

        $this->actingAs($owner)->get(route('app.tasks.index', $workspace))->assertOk();

        $space = $this->personalSpace($workspace, $owner);
        $this->assertSame(1, Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('owner_id', $owner->id)
            ->where('type', ProjectType::PERSONAL->value)
            ->count());
        $this->assertCount(4, $space->taskStatuses);
        $this->assertFalse($space->task_fields_json['assignees']['visible']);
    }

    public function test_owner_can_create_group_and_edit_a_personal_task_inline(): void
    {
        [$owner, $workspace] = $this->workspace();
        $this->actingAs($owner)->get(route('app.tasks.index', $workspace))->assertOk();
        $space = $this->personalSpace($workspace, $owner);

        $propertyId = $this->actingAs($owner)->postJson(route('internal.task-properties.store', $space), [
            'name' => 'Context',
            'type' => TaskPropertyType::SELECT->value,
            'options' => ['Office', 'Home'],
        ])->assertCreated()->json('data.public_id');
        $property = TaskProperty::query()->where('public_id', $propertyId)->firstOrFail();
        $status = $space->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        $taskId = $this->actingAs($owner)->postJson(route('internal.tasks.store', $space), [
            'title' => 'Prepare weekly notes',
            'status_public_id' => $status->public_id,
            'priority' => TaskPriority::MEDIUM->value,
            'property_values' => [$property->public_id => 'Office'],
        ])->assertCreated()->json('data.public_id');
        $task = Task::query()->where('public_id', $taskId)->firstOrFail();

        $this->actingAs($owner)->get(route('app.tasks.index', [
            'workspace' => $workspace,
            'group_by' => 'property:'.$property->public_id,
        ]))
            ->assertOk()
            ->assertSee('data-task-group-name="Office"', false)
            ->assertSee('Prepare weekly notes');

        $this->actingAs($owner)->patchJson(route('internal.tasks.fields.update', $task), [
            'field' => 'title',
            'value' => 'Prepare monthly notes',
            'version' => $task->version,
        ])->assertOk();

        $this->assertSame('Prepare monthly notes', $task->fresh()->title);
    }

    public function test_personal_task_is_hidden_from_other_workspace_members_and_project_lists(): void
    {
        [$workspaceOwner, $workspace] = $this->workspace();
        $member = $this->member($workspace);

        $this->actingAs($member)->get(route('app.tasks.index', $workspace))->assertOk();
        $space = $this->personalSpace($workspace, $member);
        $status = $space->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $task = app(CreateTask::class)->handle($space, $member, [
            'title' => 'Private appointment',
            'description' => null,
            'status_public_id' => $status->public_id,
            'category_public_id' => null,
            'priority' => TaskPriority::LOW->value,
            'start_at' => null,
            'due_at' => null,
            'estimate_minutes' => null,
            'assignee_public_ids' => [],
        ]);
        $this->actingAs($member)->postJson(route('internal.task-properties.store', $space), [
            'name' => 'Secret personal field',
            'type' => TaskPropertyType::TEXT->value,
            'options' => [],
        ])->assertCreated();

        $this->assertNull(Task::query()->visibleTo($workspaceOwner)->find($task->id));
        $this->actingAs($workspaceOwner)->get(route('app.tasks.show', $task))->assertNotFound();
        $this->actingAs($workspaceOwner)->get(route('app.projects.index', $workspace))
            ->assertOk()
            ->assertDontSee('Personal tasks');
        $this->actingAs($workspaceOwner)->get(route('app.workspaces.show', $workspace))
            ->assertOk()
            ->assertDontSee('Private appointment')
            ->assertDontSee('Secret personal field');
    }

    public function test_assigned_project_work_remains_in_its_own_view(): void
    {
        [$owner, $workspace] = $this->workspace();
        $member = $this->member($workspace);
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Website refresh',
            'description' => null,
            'color' => '#4f46e5',
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => null,
            'due_date' => null,
        ]);
        $project->memberships()->create([
            'user_id' => $member->id,
            'role' => ProjectMemberRole::MEMBER,
        ]);
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $task = app(CreateTask::class)->handle($project, $owner, [
            'title' => 'Assigned delivery work',
            'description' => null,
            'status_public_id' => $status->public_id,
            'category_public_id' => null,
            'priority' => TaskPriority::MEDIUM->value,
            'start_at' => null,
            'due_at' => null,
            'estimate_minutes' => null,
            'assignee_public_ids' => [$member->public_id],
        ]);

        $this->actingAs($member)->get(route('app.tasks.index', $workspace))
            ->assertOk()
            ->assertDontSee($task->title);
        $this->actingAs($member)->get(route('app.tasks.index', [
            'workspace' => $workspace,
            'view' => 'assigned',
        ]))
            ->assertOk()
            ->assertSee($task->title);
    }

    /** @return array{User, Workspace} */
    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Orbitra Studio',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'en',
            'week_start' => 1,
        ]);

        return [$owner, $workspace];
    }

    private function member(Workspace $workspace): User
    {
        $member = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $member->id,
            'role' => WorkspaceRole::MEMBER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        return $member;
    }

    private function personalSpace(Workspace $workspace, User $user): Project
    {
        return Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('owner_id', $user->id)
            ->where('type', ProjectType::PERSONAL->value)
            ->firstOrFail();
    }
}
