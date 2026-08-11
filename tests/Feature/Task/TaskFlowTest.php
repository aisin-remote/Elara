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
use App\Models\Task;
use App\Models\TaskMoveOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_complete_task_with_project_assignee(): void
    {
        [$owner, , $project] = $this->project();
        $assignee = $this->addProjectMember($project, ProjectMemberRole::MEMBER);
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        $this->actingAs($owner)->postJson(route('internal.tasks.store', $project), [
            ...$this->payload($status->public_id),
            'assignee_public_ids' => [$assignee->public_id],
        ])->assertCreated()->assertJsonPath('data.title', 'Design onboarding');

        $task = Task::firstOrFail();
        $this->assertDatabaseHas('task_assignees', ['task_id' => $task->id, 'user_id' => $assignee->id]);
        $this->assertSame(26, strlen($task->public_id));
    }

    public function test_task_rejects_foreign_status_assignee_invalid_priority_and_dates(): void
    {
        [$owner, , $project] = $this->project();
        [, , $otherProject] = $this->project();
        $otherStatus = $otherProject->taskStatuses()->firstOrFail();
        $outsider = User::factory()->create();

        $this->actingAs($owner)->postJson(route('internal.tasks.store', $project), [
            ...$this->payload($otherStatus->public_id),
            'priority' => 'critical',
            'start_at' => '2026-08-02 10:00:00',
            'due_at' => '2026-08-01 10:00:00',
            'assignee_public_ids' => [$outsider->public_id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['priority', 'due_at']);

        $this->actingAs($owner)->postJson(route('internal.tasks.store', $project), [
            ...$this->payload($otherStatus->public_id),
            'assignee_public_ids' => [$outsider->public_id],
        ])->assertUnprocessable()->assertJsonValidationErrors('status_public_id');
    }

    public function test_task_update_uses_optimistic_version(): void
    {
        [$owner, , $project, $task] = $this->task();
        $payload = [...$this->payload($task->status->public_id), 'title' => 'Updated title', 'version' => 1];

        $this->actingAs($owner)->patchJson(route('internal.tasks.update', $task), $payload)
            ->assertOk()->assertJsonPath('data.version', 2);
        $this->actingAs($owner)->patchJson(route('internal.tasks.update', $task), $payload)
            ->assertStatus(409)->assertJsonPath('server_version', 2);
    }

    public function test_task_can_be_duplicated_archived_and_restored(): void
    {
        [$owner, , , $task] = $this->task();
        $task->checklistItems()->create(['title' => 'Review copy', 'position' => 1024]);

        $this->actingAs($owner)->postJson(route('internal.tasks.duplicate', $task))->assertCreated();
        $copy = Task::whereKeyNot($task->id)->firstOrFail();
        $this->assertSame('Copy of '.$task->title, $copy->title);
        $this->assertCount(1, $copy->checklistItems);

        $this->actingAs($owner)->deleteJson(route('internal.tasks.destroy', $task))->assertOk();
        $archived = Task::withTrashed()->findOrFail($task->id);
        $this->actingAs($owner)->postJson(route('internal.tasks.restore', $archived))->assertOk();
        $this->assertNull($archived->fresh()->deleted_at);
    }

    public function test_move_is_ordered_idempotent_and_sets_completion(): void
    {
        [$owner, , $project, $task] = $this->task();
        $completed = $project->taskStatuses()->where('category', TaskStatusCategory::COMPLETED->value)->firstOrFail();
        $operation = (string) Str::uuid();
        $payload = [
            'status_public_id' => $completed->public_id,
            'before_task_public_id' => null,
            'after_task_public_id' => null,
            'version' => 1,
            'operation_id' => $operation,
        ];

        $this->actingAs($owner)->postJson(route('internal.tasks.move', $task), $payload)->assertOk()->assertJsonPath('data.version', 2);
        $this->actingAs($owner)->postJson(route('internal.tasks.move', $task), $payload)->assertOk()->assertJsonPath('data.version', 2);
        $this->assertSame(1, TaskMoveOperation::where('operation_id', $operation)->count());
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_workflow_statuses_can_be_created_reordered_and_archived(): void
    {
        [$owner, , $project] = $this->project();
        $response = $this->actingAs($owner)->postJson(route('internal.task-statuses.store', $project), [
            'name' => 'Review',
            'color' => '#8b5cf6',
            'category' => TaskStatusCategory::IN_PROGRESS->value,
        ])->assertCreated();
        $custom = $project->taskStatuses()->where('public_id', $response->json('data.public_id'))->firstOrFail();
        $ids = $project->taskStatuses()->active()->pluck('public_id')->reverse()->values()->all();
        $this->actingAs($owner)->postJson(route('internal.task-statuses.reorder', $project), ['status_public_ids' => $ids])->assertOk();
        $replacement = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $this->actingAs($owner)->deleteJson(route('internal.task-statuses.destroy', $custom), [
            'replacement_status_public_id' => $replacement->public_id,
        ])->assertOk();
        $this->assertNotNull($custom->fresh()->archived_at);
    }

    public function test_bulk_update_cannot_cross_project_scope(): void
    {
        [$owner, , $project, $task] = $this->task();
        [, , , $otherTask] = $this->task();

        $this->actingAs($owner)->postJson(route('internal.tasks.bulk', $project), [
            'task_public_ids' => [$task->public_id, $otherTask->public_id],
            'action' => 'priority',
            'priority' => TaskPriority::URGENT->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('task_public_ids');
    }

    public function test_checklist_comments_and_private_attachment_work(): void
    {
        Storage::fake('local');
        [$owner, , , $task] = $this->task();
        $itemResponse = $this->actingAs($owner)->postJson(route('internal.task-checklist.store', $task), ['title' => 'QA review'])->assertCreated();
        $item = $task->checklistItems()->where('public_id', $itemResponse->json('data.public_id'))->firstOrFail();
        $this->actingAs($owner)->patchJson(route('internal.task-checklist.update', $item), ['title' => 'QA review', 'is_completed' => true])->assertOk();
        $this->assertNotNull($item->fresh()->completed_at);

        $commentResponse = $this->actingAs($owner)->postJson(route('internal.task-comments.store', $task), ['body' => 'Looks ready.'])->assertCreated();
        $comment = $task->comments()->where('public_id', $commentResponse->json('data.public_id'))->firstOrFail();
        $this->actingAs($owner)->patchJson(route('internal.task-comments.update', $comment), ['body' => 'Ready to ship.'])->assertOk();

        $fileResponse = $this->actingAs($owner)->postJson(route('internal.task-attachments.store', $task), [
            'attachment' => UploadedFile::fake()->image('preview.png'),
        ])->assertCreated();
        $file = $task->files()->where('public_id', $fileResponse->json('data.public_id'))->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->actingAs($owner)->get(route('internal.files.download', $file))->assertOk();
        $this->actingAs($owner)->deleteJson(route('internal.files.destroy', $file))->assertOk();
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_viewer_cannot_mutate_and_cross_workspace_binding_is_hidden(): void
    {
        [$owner, $workspace, $project, $task] = $this->task();
        $viewer = $this->addProjectMember($project, ProjectMemberRole::VIEWER, WorkspaceRole::VIEWER);
        [, , , $otherTask] = $this->task();

        $this->actingAs($viewer)->patchJson(route('internal.tasks.update', $task), [
            ...$this->payload($task->status->public_id),
            'version' => 1,
        ])->assertForbidden();
        $this->actingAs($owner)->get(route('app.tasks.show', $otherTask))->assertNotFound();
        $this->actingAs($owner)->get('/app/tasks/'.$task->id)->assertNotFound();
        $this->actingAs($viewer)->get(route('app.projects.board', [$workspace, $project]))->assertOk();
    }

    public function test_list_board_and_task_detail_render_same_task(): void
    {
        [$owner, $workspace, $project, $task] = $this->task();

        $this->actingAs($owner)->get(route('app.projects.tasks', [$workspace, $project]))->assertOk()->assertSee($task->title);
        $this->actingAs($owner)->get(route('app.projects.board', [$workspace, $project]))
            ->assertOk()
            ->assertSee($task->title)
            ->assertSee('Right →');
        $this->actingAs($owner)->get(route('app.tasks.index', $workspace))->assertOk()->assertSee($task->title);
        $this->actingAs($owner)->get(route('app.tasks.show', $task))->assertOk()->assertSee($task->title);
    }

    public function test_create_query_opens_task_form_as_a_modal(): void
    {
        [$owner, $workspace, $project] = $this->project();

        $this->actingAs($owner)
            ->get(route('app.projects.tasks', [$workspace, $project]).'?create=1')
            ->assertOk()
            ->assertSee('x-init="$nextTick(() => $el.showModal())"', false);
    }

    private function task(): array
    {
        [$owner, $workspace, $project] = $this->project();
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $task = app(CreateTask::class)->handle($project, $owner, $this->payload($status->public_id));

        return [$owner, $workspace, $project, $task];
    }

    private function project(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => fake()->company(), 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => fake()->words(3, true), 'description' => null, 'color' => '#4f46e5',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => null, 'due_date' => null,
        ]);

        return [$owner, $workspace, $project];
    }

    private function addProjectMember($project, ProjectMemberRole $projectRole, WorkspaceRole $workspaceRole = WorkspaceRole::MEMBER): User
    {
        $user = User::factory()->create();
        $project->workspace->memberships()->create([
            'user_id' => $user->id, 'role' => $workspaceRole, 'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);
        $project->memberships()->create(['user_id' => $user->id, 'role' => $projectRole]);

        return $user;
    }

    private function payload(string $statusPublicId): array
    {
        return [
            'title' => 'Design onboarding',
            'description' => 'Create the first-run flow.',
            'status_public_id' => $statusPublicId,
            'category_public_id' => null,
            'priority' => TaskPriority::HIGH->value,
            'start_at' => '2026-08-01 09:00:00',
            'due_at' => '2026-08-03 17:00:00',
            'estimate_minutes' => 240,
            'assignee_public_ids' => [],
        ];
    }
}
