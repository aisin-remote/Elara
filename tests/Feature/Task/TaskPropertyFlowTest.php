<?php

namespace Tests\Feature\Task;

use App\Actions\Project\CreateProject;
use App\Actions\Task\CreateTask;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskPropertyType;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskProperty;
use App\Models\TaskPropertyValue;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPropertyFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_list_is_the_notion_style_database_and_old_board_redirects_to_it(): void
    {
        [$owner, $workspace, $project, $task] = $this->task();

        $response = $this->actingAs($owner)->postJson(route('internal.task-properties.store', $project), [
            'name' => 'Ticket reference',
            'type' => TaskPropertyType::TEXT->value,
            'options' => [],
        ])->assertCreated();
        $property = TaskProperty::where('public_id', $response->json('data.public_id'))->firstOrFail();

        $this->actingAs($owner)->putJson(route('internal.task-properties.values.update', [$task, $property]), [
            'value' => 'INC-42',
        ])->assertOk()->assertJsonPath('data.value', 'INC-42');

        $this->actingAs($owner)->get(route('app.projects.tasks', [$workspace, $project]))
            ->assertOk()
            ->assertSee('Project database')
            ->assertSee('Add property')
            ->assertDontSee('Customize table')
            ->assertSee('Ticket reference')
            ->assertSee('inlineTaskProperty', false)
            ->assertDontSee('>Board<', false);

        $this->actingAs($owner)->get(route('app.projects.board', [$workspace, $project]))
            ->assertRedirect(route('app.projects.tasks', [$workspace, $project]));
    }

    public function test_select_and_checklist_values_are_validated_and_types_can_be_changed(): void
    {
        [$owner, , $project, $task] = $this->task();
        $select = $this->property($owner, $project, 'Work type', TaskPropertyType::SELECT, ['Bug', 'Feature']);
        $checklist = $this->property($owner, $project, 'QA ready', TaskPropertyType::CHECKBOX);

        $this->actingAs($owner)->putJson(route('internal.task-properties.values.update', [$task, $select]), [
            'value' => 'Unknown',
        ])->assertUnprocessable()->assertJsonValidationErrors('value');

        $this->actingAs($owner)->putJson(route('internal.task-properties.values.update', [$task, $select]), [
            'value' => 'Bug',
        ])->assertOk();
        $this->actingAs($owner)->putJson(route('internal.task-properties.values.update', [$task, $checklist]), [
            'value' => true,
        ])->assertOk();

        $this->assertSame('Bug', TaskPropertyValue::where('task_property_id', $select->id)->firstOrFail()->value_json);
        $this->assertTrue(TaskPropertyValue::where('task_property_id', $checklist->id)->firstOrFail()->value_json);

        $this->actingAs($owner)->patchJson(route('internal.task-properties.update', $select), [
            'name' => 'Work type',
            'type' => TaskPropertyType::TEXT->value,
            'options' => [],
        ])->assertOk();
        $this->assertDatabaseMissing('task_property_values', ['task_property_id' => $select->id]);
    }

    public function test_only_workflow_managers_define_properties_and_values_cannot_cross_projects(): void
    {
        [$owner, $workspace, $project, $task] = $this->task();
        $viewer = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::VIEWER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        $project->memberships()->create(['user_id' => $viewer->id, 'role' => ProjectMemberRole::VIEWER]);

        $this->actingAs($viewer)->postJson(route('internal.task-properties.store', $project), [
            'name' => 'Forbidden',
            'type' => TaskPropertyType::TEXT->value,
            'options' => [],
        ])->assertForbidden();

        $property = $this->property($owner, $project, 'Reference', TaskPropertyType::TEXT);
        $this->actingAs($viewer)->putJson(route('internal.task-properties.values.update', [$task, $property]), [
            'value' => 'Nope',
        ])->assertForbidden();

        $otherProject = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Other project',
            'description' => null,
            'color' => '#64748b',
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => null,
            'due_date' => null,
        ]);
        $otherProperty = $this->property($owner, $otherProject, 'Other reference', TaskPropertyType::TEXT);
        $this->actingAs($owner)->putJson(route('internal.task-properties.values.update', [$task, $otherProperty]), [
            'value' => 'Crossed',
        ])->assertForbidden();
    }

    public function test_system_fields_can_be_renamed_hidden_and_restored_but_name_stays_required(): void
    {
        [$owner, $workspace, $project] = $this->task();

        $this->actingAs($owner)->patchJson(route('internal.task-fields.update', [$project, 'description']), [
            'name' => 'Work summary',
            'visible' => true,
        ])->assertOk();
        $this->actingAs($owner)->patchJson(route('internal.task-fields.update', [$project, 'due_at']), [
            'name' => 'Deadline',
            'visible' => false,
        ])->assertOk();
        $this->actingAs($owner)->patchJson(route('internal.task-fields.update', [$project, 'title']), [
            'name' => 'Task title',
            'visible' => false,
        ])->assertOk();

        $this->actingAs($owner)->get(route('app.projects.tasks', [$workspace, $project]))
            ->assertOk()
            ->assertSee('Work summary')
            ->assertSee('Task title')
            ->assertSee('Hidden system properties')
            ->assertSee('Deadline');

        $settings = $project->fresh()->task_fields_json;
        $this->assertFalse($settings['due_at']['visible']);
        $this->assertTrue($settings['title']['visible']);
    }

    public function test_add_task_form_uses_custom_schema_and_saves_property_values_atomically(): void
    {
        [$owner, $workspace, $project] = $this->task();
        $reference = $this->property($owner, $project, 'Ticket reference', TaskPropertyType::TEXT);
        $workType = $this->property($owner, $project, 'Work type', TaskPropertyType::SELECT, ['Bug', 'Feature']);
        $ready = $this->property($owner, $project, 'QA ready', TaskPropertyType::CHECKBOX);
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        $this->actingAs($owner)->get(route('app.projects.tasks', [$workspace, $project]).'?create=1')
            ->assertOk()
            ->assertSee('Ticket reference')
            ->assertSee('Work type')
            ->assertSee('QA ready');

        $this->actingAs($owner)->postJson(route('internal.tasks.store', $project), [
            'title' => 'Invalid custom value',
            'status_public_id' => $status->public_id,
            'property_values' => [$workType->public_id => 'Unknown'],
        ])->assertUnprocessable()->assertJsonValidationErrors('property_values.'.$workType->public_id);
        $this->assertDatabaseMissing('tasks', ['title' => 'Invalid custom value']);

        $this->actingAs($owner)->postJson(route('internal.tasks.store', $project), [
            'title' => 'Investigate checkout bug',
            'status_public_id' => $status->public_id,
            'property_values' => [
                $reference->public_id => 'INC-104',
                $workType->public_id => 'Bug',
                $ready->public_id => '1',
            ],
        ])->assertCreated();

        $task = Task::where('title', 'Investigate checkout bug')->firstOrFail();
        $this->assertSame(TaskPriority::MEDIUM, $task->priority);
        $this->assertSame('INC-104', TaskPropertyValue::whereBelongsTo($task)->whereBelongsTo($reference, 'property')->firstOrFail()->value_json);
        $this->assertSame('Bug', TaskPropertyValue::whereBelongsTo($task)->whereBelongsTo($workType, 'property')->firstOrFail()->value_json);
        $this->assertTrue(TaskPropertyValue::whereBelongsTo($task)->whereBelongsTo($ready, 'property')->firstOrFail()->value_json);
    }

    public function test_tasks_can_be_grouped_by_priority_or_any_visible_select_property(): void
    {
        [$owner, $workspace, $project, $task] = $this->task();
        $stage = $this->property($owner, $project, 'Status', TaskPropertyType::SELECT, ['Pending', 'Complete']);
        $notes = $this->property($owner, $project, 'Internal note', TaskPropertyType::TEXT);
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $withoutStage = app(CreateTask::class)->handle($project, $owner, [
            'title' => 'Task without a selected stage',
            'description' => null,
            'status_public_id' => $status->public_id,
            'category_public_id' => null,
            'priority' => TaskPriority::LOW->value,
            'start_at' => null,
            'due_at' => null,
            'estimate_minutes' => null,
            'assignee_public_ids' => [$owner->public_id],
        ]);

        $this->actingAs($owner)->putJson(route('internal.task-properties.values.update', [$task, $stage]), [
            'value' => 'Complete',
        ])->assertOk();

        $propertyResponse = $this->actingAs($owner)->get(route('app.projects.tasks', [$workspace, $project]).'?group_by=property:'.$stage->public_id)
            ->assertOk()
            ->assertSee('data-task-group-name="Pending"', false)
            ->assertSee('data-task-group-name="Complete"', false)
            ->assertSee('data-task-group-name="No selection"', false)
            ->assertSee('reloadOnSave: true', false)
            ->assertDontSee('value="property:'.$notes->public_id.'"', false);
        $propertyResponse->assertSeeInOrder([
            'data-task-group-name="Complete"',
            $task->title,
            'data-task-group-name="No selection"',
            $withoutStage->title,
        ], false);

        $priorityResponse = $this->actingAs($owner)->get(route('app.projects.tasks', [$workspace, $project]).'?group_by=priority')
            ->assertOk()
            ->assertSee('data-task-group-name="Low"', false)
            ->assertSee('data-task-group-name="Medium"', false)
            ->assertSee('data-task-group-name="High"', false)
            ->assertSee('data-task-group-name="Urgent"', false);
        $priorityResponse->assertSeeInOrder([
            'data-task-group-name="Low"',
            $withoutStage->title,
            'data-task-group-name="Medium"',
            $task->title,
        ], false);
    }

    public function test_required_task_fields_are_saved_inline_with_one_shared_version(): void
    {
        [$owner, $workspace, $project, $task] = $this->task();
        $assignee = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $assignee->id,
            'role' => WorkspaceRole::MEMBER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        $project->memberships()->create([
            'user_id' => $assignee->id,
            'role' => ProjectMemberRole::MEMBER,
        ]);

        $endpoint = route('internal.tasks.fields.update', $task);
        $this->actingAs($owner)->patchJson($endpoint, [
            'field' => 'title',
            'value' => 'Inline task title',
            'version' => 1,
        ])->assertOk()->assertJsonPath('data.version', 2);
        $this->actingAs($owner)->patchJson($endpoint, [
            'field' => 'description',
            'value' => 'Edited directly from the project table.',
            'version' => 2,
        ])->assertOk()->assertJsonPath('data.version', 3);
        $this->actingAs($owner)->patchJson($endpoint, [
            'field' => 'due_at',
            'value' => '2026-08-20T17:00',
            'version' => 3,
        ])->assertOk()->assertJsonPath('data.version', 4);
        $this->actingAs($owner)->patchJson($endpoint, [
            'field' => 'priority',
            'value' => TaskPriority::URGENT->value,
            'version' => 4,
        ])->assertOk()->assertJsonPath('data.version', 5);
        $this->actingAs($owner)->patchJson($endpoint, [
            'field' => 'assignees',
            'value' => [$assignee->public_id],
            'version' => 5,
        ])->assertOk()->assertJsonPath('data.version', 6);

        $task = $task->fresh();
        $this->assertSame('Inline task title', $task->title);
        $this->assertSame('Edited directly from the project table.', $task->description);
        $this->assertSame('2026-08-20 17:00:00', $task->due_at->format('Y-m-d H:i:s'));
        $this->assertSame(TaskPriority::URGENT, $task->priority);
        $this->assertEquals([$assignee->id], $task->assignees()->pluck('users.id')->all());

        $this->actingAs($owner)->get(route('app.projects.tasks', [$workspace, $project]))
            ->assertOk()
            ->assertSee('inlineTaskRow', false)
            ->assertSee('x-model="values.title"', false)
            ->assertSee('x-model="values.assignees"', false);
    }

    public function test_inline_task_fields_reject_stale_edits_invalid_assignees_and_viewers(): void
    {
        [$owner, $workspace, $project, $task] = $this->task();
        $endpoint = route('internal.tasks.fields.update', $task);

        $this->actingAs($owner)->patchJson($endpoint, [
            'field' => 'title',
            'value' => 'First edit',
            'version' => 1,
        ])->assertOk();
        $this->actingAs($owner)->patchJson($endpoint, [
            'field' => 'description',
            'value' => 'Stale edit',
            'version' => 1,
        ])->assertConflict()->assertJsonPath('server_version', 2);

        $outsider = User::factory()->create();
        $this->actingAs($owner)->patchJson($endpoint, [
            'field' => 'assignees',
            'value' => [$outsider->public_id],
            'version' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('assignee_public_ids');

        $viewer = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::VIEWER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        $project->memberships()->create([
            'user_id' => $viewer->id,
            'role' => ProjectMemberRole::VIEWER,
        ]);
        $this->actingAs($viewer)->patchJson($endpoint, [
            'field' => 'priority',
            'value' => TaskPriority::LOW->value,
            'version' => 2,
        ])->assertForbidden();
    }

    private function property(User $owner, Project $project, string $name, TaskPropertyType $type, array $options = []): TaskProperty
    {
        $publicId = $this->actingAs($owner)->postJson(route('internal.task-properties.store', $project), [
            'name' => $name,
            'type' => $type->value,
            'options' => $options,
        ])->assertCreated()->json('data.public_id');

        return TaskProperty::where('public_id', $publicId)->firstOrFail();
    }

    /** @return array{User, Workspace, Project, Task} */
    private function task(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio',
            'timezone' => 'UTC',
            'locale' => 'en',
            'week_start' => 1,
        ]);
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Website Redesign',
            'description' => null,
            'color' => '#2eb0fb',
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => null,
            'due_date' => null,
        ]);
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $task = app(CreateTask::class)->handle($project, $owner, [
            'title' => 'Design onboarding',
            'description' => 'Prepare the onboarding experience.',
            'status_public_id' => $status->public_id,
            'category_public_id' => null,
            'priority' => TaskPriority::MEDIUM->value,
            'start_at' => null,
            'due_at' => null,
            'estimate_minutes' => null,
            'assignee_public_ids' => [$owner->public_id],
        ]);

        return [$owner, $workspace, $project, $task];
    }
}
