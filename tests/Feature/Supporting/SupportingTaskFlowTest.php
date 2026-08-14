<?php

namespace Tests\Feature\Supporting;

use App\Actions\Workspace\CreateWorkspace;
use App\Enums\SupportingTaskCategory;
use App\Enums\SupportingTaskStatus;
use App\Enums\TaskPriority;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\SupportingTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportingTaskFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributor_can_register_complete_and_archive_supporting_work(): void
    {
        [$owner, $workspace] = $this->workspace();
        $assignee = $this->member($workspace, WorkspaceRole::MEMBER);

        $this->actingAs($owner)->get(route('app.supporting.create', $workspace))
            ->assertOk()->assertSee('Register operational work');

        $response = $this->actingAs($owner)->postJson(route('internal.supporting-tasks.store', $workspace), [
            ...$this->payload(),
            'assignee_public_id' => $assignee->public_id,
        ])->assertCreated()
            ->assertJsonPath('data.status', SupportingTaskStatus::TODO->value)
            ->assertJsonPath('data.assignee', $assignee->name);

        $task = SupportingTask::where('public_id', $response->json('data.public_id'))->firstOrFail();
        $this->assertSame(26, strlen($task->public_id));
        $this->assertNull($task->completed_at);
        $this->actingAs($owner)->get(route('app.supporting.edit', [$workspace, $task]))
            ->assertOk()->assertSee($task->title);

        $this->actingAs($assignee)->patchJson(route('internal.supporting-tasks.update', $task), [
            ...$this->payload(),
            'status' => SupportingTaskStatus::COMPLETED->value,
            'assignee_public_id' => $assignee->public_id,
        ])->assertOk()->assertJsonPath('data.status', SupportingTaskStatus::COMPLETED->value);
        $this->assertNotNull($task->fresh()->completed_at);

        $this->actingAs($owner)->deleteJson(route('internal.supporting-tasks.destroy', $task))->assertOk();
        $this->assertSoftDeleted('supporting_tasks', ['id' => $task->id]);
    }

    public function test_list_is_workspace_scoped_and_filters_real_supporting_tasks(): void
    {
        [$owner, $workspace] = $this->workspace();
        SupportingTask::create([
            'workspace_id' => $workspace->id,
            'creator_id' => $owner->id,
            'title' => 'Repair finance printer',
            'category' => SupportingTaskCategory::HARDWARE,
            'priority' => TaskPriority::HIGH,
            'status' => SupportingTaskStatus::IN_PROGRESS,
            'due_date' => today()->addDay(),
        ]);
        [, $otherWorkspace] = $this->workspace();
        SupportingTask::create([
            'workspace_id' => $otherWorkspace->id,
            'title' => 'Hidden support work',
            'category' => SupportingTaskCategory::OTHER,
            'priority' => TaskPriority::LOW,
            'status' => SupportingTaskStatus::TODO,
        ]);

        $this->actingAs($owner)->get(route('app.supporting.index', [$workspace, 'category' => SupportingTaskCategory::HARDWARE->value]))
            ->assertOk()
            ->assertSee('Supporting')
            ->assertSee('Repair finance printer')
            ->assertDontSee('Hidden support work');
    }

    public function test_viewer_cannot_mutate_and_foreign_assignee_is_rejected(): void
    {
        [$owner, $workspace] = $this->workspace();
        $viewer = $this->member($workspace, WorkspaceRole::VIEWER);
        $outsider = User::factory()->create();

        $this->actingAs($viewer)->postJson(route('internal.supporting-tasks.store', $workspace), $this->payload())->assertForbidden();
        $this->actingAs($owner)->postJson(route('internal.supporting-tasks.store', $workspace), [
            ...$this->payload(),
            'assignee_public_id' => $outsider->public_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('assignee_public_id');
    }

    public function test_requester_can_send_a_quick_support_request(): void
    {
        [, $workspace] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        $this->actingAs($requester)->get(route('desk.supporting.create', $workspace))
            ->assertOk()
            ->assertSee('Request operational support')
            ->assertDontSee('Sent directly to ITD')
            ->assertSee('How it works')
            ->assertSee('Request details');
        $this->actingAs($requester)->post(route('desk.supporting.store', $workspace), [
            'title' => 'Repair meeting room printer',
            'description' => 'The printer is online but every print job remains queued.',
            'category' => SupportingTaskCategory::HARDWARE->value,
            'priority' => TaskPriority::MEDIUM->value,
        ])->assertRedirect();

        $task = SupportingTask::where('creator_id', $requester->id)->firstOrFail();
        $this->assertSame(SupportingTaskStatus::TODO, $task->status);
        $this->actingAs($requester)->get(route('desk.supporting.show', $task))
            ->assertOk()
            ->assertSee('All requests')
            ->assertSee('Request details')
            ->assertSee('Waiting for ITD assignment');
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => fake()->company(), 'timezone' => 'Asia/Jakarta', 'locale' => 'en', 'week_start' => 1,
        ]);

        return [$owner, $workspace];
    }

    private function member($workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $user->id,
            'role' => $role,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function payload(): array
    {
        return [
            'title' => 'Prepare quarterly review PowerPoint',
            'description' => 'Create the deck requested for the quarterly business review.',
            'category' => SupportingTaskCategory::PRESENTATION->value,
            'priority' => TaskPriority::MEDIUM->value,
            'status' => SupportingTaskStatus::TODO->value,
            'assignee_public_id' => null,
            'due_date' => '2026-08-15',
        ];
    }
}
