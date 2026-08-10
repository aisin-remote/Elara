<?php

namespace Tests\Feature\Workspace;

use App\Actions\Project\CreateProject;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequesterAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_is_denied_every_delivery_route_including_deep_links(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $task = $this->task($project, $owner);

        $routes = [
            route('app.dashboard'),
            route('app.workspaces.show', $workspace),
            route('app.projects.index', $workspace),
            route('app.projects.show', $project),
            route('app.projects.timeline', [$workspace, $project]),
            route('app.tasks.index', $workspace),
            route('app.tasks.show', $task),
            route('app.workspaces.team', $workspace),
            route('app.messages.index', $workspace),
            route('app.schedule.index', $workspace),
            route('app.performance.index', $workspace),
            route('app.settings.profile', $workspace),
        ];

        foreach ($routes as $url) {
            $this->actingAs($requester)->get($url)->assertForbidden();
        }
    }

    public function test_requester_reaches_the_desk_and_delivery_roles_do_not_lose_the_app(): void
    {
        [$owner, $workspace] = $this->project();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        // The desk is split by type now, so the empty state names the tab it is speaking for.
        $this->actingAs($requester)->get(route('desk.index'))->assertOk()->assertSee('Belum ada permintaan fitur');

        foreach ([WorkspaceRole::SUPERVISOR, WorkspaceRole::MANAGER, WorkspaceRole::MEMBER, WorkspaceRole::VIEWER] as $role) {
            $this->actingAs($this->member($workspace, $role))
                ->get(route('app.workspaces.show', $workspace))
                ->assertOk();
        }

        $this->actingAs($owner)->get(route('app.workspaces.show', $workspace))->assertOk();
    }

    public function test_requester_cannot_write_through_endpoints_that_only_excluded_viewers(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $task = $this->task($project, $owner);

        // Every one of these guarded with "role !== viewer" before PRD-01, which would have
        // granted a requester write access without any UI ever showing the control.
        $this->actingAs($requester)->postJson(route('internal.conversations.store', $workspace), [
            'type' => 'direct', 'participant_public_ids' => [$owner->public_id],
        ])->assertForbidden();

        $this->actingAs($requester)->postJson(route('internal.schedule-events.store', $workspace), [
            'title' => 'Unauthorised', 'description' => null, 'project_public_id' => null,
            'start_at' => now()->addDay()->toDateTimeString(), 'end_at' => now()->addDay()->addHour()->toDateTimeString(),
            'timezone' => 'UTC', 'color' => '#2eb0fb', 'meeting_url' => null, 'attendee_public_ids' => [],
        ])->assertForbidden();

        $this->actingAs($requester)->patchJson(route('internal.tasks.update', $task), [
            'title' => 'Hijacked', 'version' => $task->version,
        ])->assertForbidden();
    }

    public function test_login_sends_a_requester_to_the_desk_and_ignores_a_stored_app_url(): void
    {
        [, $workspace] = $this->project();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        // Hitting a delivery URL first stores it as intended(); the requester must not be
        // handed back a 403 immediately after a successful sign-in.
        $this->get(route('app.workspaces.show', $workspace))->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => $requester->email, 'password' => 'password'])
            ->assertRedirect('/desk');
    }

    public function test_role_helpers_answer_for_every_case(): void
    {
        foreach (WorkspaceRole::cases() as $role) {
            $this->assertIsBool($role->canContribute());
            $this->assertIsBool($role->canAccessDeliveryDesk());
            $this->assertNotSame('', $role->label());
        }

        $this->assertFalse(WorkspaceRole::REQUESTER->canAccessDeliveryDesk());
        $this->assertFalse(WorkspaceRole::REQUESTER->canContribute());
        $this->assertFalse(WorkspaceRole::VIEWER->canContribute());
        $this->assertTrue(WorkspaceRole::SUPERVISOR->canContribute());
        $this->assertTrue(WorkspaceRole::MANAGER->canContribute());
        $this->assertArrayNotHasKey('owner', WorkspaceRole::assignable());
        $this->assertArrayHasKey('requester', WorkspaceRole::assignable());
    }

    private function project(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Delivery', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Inventory', 'description' => null, 'color' => '#2eb0fb',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => null, 'due_date' => null,
        ]);

        return [$owner, $workspace, $project];
    }

    private function member($workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $user->id, 'role' => $role,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);

        return $user;
    }

    private function task($project, User $creator): Task
    {
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        return $project->tasks()->create([
            'workspace_id' => $project->workspace_id,
            'status_id' => $status->id,
            'creator_id' => $creator->id,
            'title' => 'Existing work',
            'priority' => TaskPriority::MEDIUM,
            'position' => 1024,
        ]);
    }
}
