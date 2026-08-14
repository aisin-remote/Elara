<?php

namespace Tests\Feature\Workspace;

use App\Actions\Project\ArchiveProject;
use App\Actions\Project\CreateProject;
use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectRequestStatus;
use App\Enums\ProjectStatus;
use App\Enums\RequestUrgency;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Feature;
use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequesterItTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_expand_department_projects_into_task_timelines_without_internal_details(): void
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
        $requesterWorkspace->update([
            'organization_department_id' => 10,
            'organization_department_code' => 'FIN',
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
        ProjectRequest::create([
            'workspace_id' => $delivery->id,
            'requester_id' => $requester->id,
            'title' => 'Inventory Modernization',
            'benefit' => 'Faster stock reconciliation for the finance department.',
            'concept' => 'Modernize inventory reconciliation.',
            'business_process' => 'Finance reconciles stock manually.',
            'flow' => 'Submit, reconcile, and verify.',
            'status' => ProjectRequestStatus::IN_PROGRESS,
            'project_id' => $project->id,
            'requester_department_external_id' => 10,
            'requester_department_code' => 'FIN',
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

        $system = app(CreateSystem::class)->handle($delivery, $owner, [
            'name' => 'Finance Core',
            'description' => null,
            'color' => '#0ea5e9',
            'pic_id' => $itMember->id,
        ]);
        $feature = Feature::create([
            'workspace_id' => $delivery->id,
            'project_id' => $system->id,
            'name' => 'Automated journal export',
        ]);
        FeatureRequest::create([
            'workspace_id' => $delivery->id,
            'project_id' => $system->id,
            'requester_id' => $requester->id,
            'title' => 'Automated journal export',
            'problem' => 'Finance exports journal entries manually every day.',
            'desired_outcome' => 'Journal entries can be exported automatically.',
            'benefit' => 'Reduces repetitive finance work.',
            'urgency' => RequestUrgency::NORMAL,
            'status' => FeatureRequestStatus::IN_PROGRESS,
            'feature_id' => $feature->id,
            'requester_department_external_id' => 10,
            'requester_department_code' => 'FIN',
        ]);
        $featureStatus = $system->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $featureTask = $system->tasks()->create([
            'workspace_id' => $delivery->id,
            'feature_id' => $feature->id,
            'status_id' => $featureStatus->id,
            'creator_id' => $owner->id,
            'title' => 'Build automated journal export',
            'priority' => TaskPriority::MEDIUM,
            'start_at' => now(),
            'due_at' => now()->addDays(6),
            'position' => 1024,
        ]);
        $featureTask->assignees()->attach($itMember->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);

        $otherDepartmentProject = app(CreateProject::class)->handle($delivery, $owner, [
            'name' => 'Human Resources Portal',
            'description' => null,
            'color' => '#f97316',
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
        ]);
        ProjectRequest::create([
            'workspace_id' => $delivery->id,
            'requester_id' => $requester->id,
            'title' => 'Human Resources Portal',
            'benefit' => 'Improves HR service.',
            'concept' => 'An employee self-service portal.',
            'business_process' => 'HR handles requests manually.',
            'flow' => 'Submit and resolve.',
            'status' => ProjectRequestStatus::IN_PROGRESS,
            'project_id' => $otherDepartmentProject->id,
            'requester_department_external_id' => 20,
            'requester_department_code' => 'HR',
        ]);
        $otherStatus = $otherDepartmentProject->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $otherTask = $otherDepartmentProject->tasks()->create([
            'workspace_id' => $delivery->id,
            'status_id' => $otherStatus->id,
            'creator_id' => $owner->id,
            'title' => 'Build employee onboarding form',
            'priority' => TaskPriority::MEDIUM,
            'start_at' => now(),
            'due_at' => now()->addDays(4),
            'position' => 1024,
        ]);
        $otherTask->assignees()->attach($itMember->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);

        $this->actingAs($requester)
            ->get(route('desk.it-timeline'))
            ->assertOk()
            ->assertSee('IT timeline')
            ->assertSee('Inventory Modernization')
            ->assertSee('Build stock reconciliation flow')
            ->assertSee('Toggle Inventory Modernization tasks')
            ->assertDontSee('Task timeline per member')
            ->assertDontSee('Build automated journal export')
            ->assertDontSee('Nadia Putri')
            ->assertDontSee('Internal project description')
            ->assertDontSee('Secret implementation notes')
            ->assertDontSee('Human Resources Portal')
            ->assertDontSee('Build employee onboarding form')
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

        $requesterWorkspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Workspace without department', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        $requester = User::factory()->create();
        $requesterWorkspace->memberships()->create([
            'user_id' => $requester->id,
            'role' => WorkspaceRole::REQUESTER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        config()->set('organization.required', true);
        $this->actingAs($requester)->get(route('desk.it-timeline'))->assertForbidden();
    }
}
