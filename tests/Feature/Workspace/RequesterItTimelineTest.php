<?php

namespace Tests\Feature\Workspace;

use App\Actions\Project\ArchiveProject;
use App\Actions\Project\CreateProject;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequesterItTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_see_project_and_member_task_timelines_without_internal_details(): void
    {
        $owner = User::factory()->create();
        $delivery = app(CreateWorkspace::class)->handle($owner, [
            'name' => "ITD's Workspace", 'timezone' => 'Asia/Jakarta', 'locale' => 'en', 'week_start' => 1,
        ]);
        $delivery->update(['organization_department_code' => 'ITD']);
        config()->set('organization.workspace_public_id', $delivery->public_id);

        $requesterWorkspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => "FIN's Workspace", 'timezone' => 'Asia/Jakarta', 'locale' => 'en', 'week_start' => 1,
        ]);
        $requester = User::factory()->create();
        $requesterWorkspace->memberships()->create([
            'user_id' => $requester->id,
            'role' => WorkspaceRole::REQUESTER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        $itMember = User::factory()->create([
            'first_name' => 'Nadia',
            'last_name' => 'Putri',
            'job_title' => 'Application Developer',
        ]);
        $delivery->memberships()->create([
            'user_id' => $itMember->id,
            'role' => WorkspaceRole::MEMBER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        $project = app(CreateProject::class)->handle($delivery, $owner, [
            'name' => 'Inventory Modernization',
            'description' => 'Internal project description',
            'color' => '#8b5cf6',
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->subDay()->toDateString(),
            'due_date' => now()->addDays(20)->toDateString(),
        ]);
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $task = $project->tasks()->create([
            'workspace_id' => $delivery->id,
            'status_id' => $status->id,
            'creator_id' => $owner->id,
            'title' => 'Build stock reconciliation flow',
            'description' => 'Secret implementation notes',
            'priority' => TaskPriority::MEDIUM,
            'start_at' => now(),
            'due_at' => now()->addDays(5),
            'position' => 1024,
        ]);
        $task->assignees()->attach($itMember->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);

        $this->actingAs($requester)
            ->get(route('desk.it-timeline'))
            ->assertOk()
            ->assertSee('IT timeline')
            ->assertSee('Inventory Modernization')
            ->assertSee('Build stock reconciliation flow')
            ->assertSee('Nadia Putri')
            ->assertSee('Application Developer')
            ->assertDontSee('Internal project description')
            ->assertDontSee('Secret implementation notes')
            ->assertDontSee(route('app.tasks.show', $task), false);

        app(ArchiveProject::class)->archive($project, $owner);

        $this->actingAs($requester)
            ->get(route('desk.it-timeline'))
            ->assertOk()
            ->assertDontSee('Inventory Modernization')
            ->assertDontSee('Build stock reconciliation flow');
    }

    public function test_delivery_users_and_users_without_a_requester_membership_cannot_open_it_timeline(): void
    {
        $owner = User::factory()->create();
        $delivery = app(CreateWorkspace::class)->handle($owner, [
            'name' => "ITD's Workspace", 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        config()->set('organization.workspace_public_id', $delivery->public_id);

        $this->actingAs($owner)->get(route('desk.it-timeline'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('desk.it-timeline'))->assertForbidden();
    }
}
