<?php

namespace Tests\Feature\Workspace;

use App\Actions\Project\CreateProject;
use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\CheckpointStatus;
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
use App\Models\Task;
use App\Models\User;
use App\Models\ValidationCheckpoint;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequesterMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_monitoring_uses_checklists_and_dependencies_without_exposing_task_titles(): void
    {
        [$workspace, $owner, $system, $requester] = $this->featureSystem();
        $feature = Feature::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'name' => 'Monthly stock export',
            'status' => 'in_progress',
        ]);
        $request = $this->featureRequest($workspace, $system, $requester, $feature, $owner);
        $status = $system->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $first = $this->task($request, $status->id, $requester, 'Secret backend implementation');
        $second = $this->task($request, $status->id, $requester, 'Secret interface implementation');
        $first->checklistItems()->createMany([
            ['title' => 'First step', 'position' => 1024, 'is_completed' => true, 'completed_at' => now()],
            ['title' => 'Second step', 'position' => 2048],
        ]);
        $second->checklistItems()->createMany([
            ['title' => 'Third step', 'position' => 1024],
            ['title' => 'Fourth step', 'position' => 2048],
        ]);
        $second->dependencies()->attach($first);

        $this->actingAs($requester)
            ->get(route('desk.requests.show', $request))
            ->assertOk()
            ->assertSee('Request progress')
            ->assertSee('Updates automatically every 10 seconds')
            ->assertDontSee('Secret backend implementation');

        $this->actingAs($requester)
            ->getJson(route('internal.requests.monitoring', $request))
            ->assertOk()
            ->assertJsonPath('current_stage', 'Delivery')
            ->assertJsonPath('progress', 25)
            ->assertJsonPath('tasks.completed', 0)
            ->assertJsonPath('tasks.total', 2)
            ->assertJsonPath('tasks.blocked', 1)
            ->assertJsonPath('stages.4.state', 'current')
            ->assertDontSee('Secret backend implementation');
    }

    public function test_open_validation_becomes_the_live_stage_and_links_to_the_requesters_action(): void
    {
        [$workspace, $owner, $system, $requester] = $this->featureSystem();
        $feature = Feature::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'name' => 'Monthly stock export',
            'status' => 'in_progress',
        ]);
        $request = $this->featureRequest($workspace, $system, $requester, $feature, $owner);
        $status = $system->taskStatuses()->where('category', TaskStatusCategory::COMPLETED->value)->firstOrFail();
        $task = $this->task($request, $status->id, $requester, 'Private validation deliverable');
        $task->forceFill(['completed_at' => now()])->save();
        ValidationCheckpoint::create([
            'workspace_id' => $workspace->id,
            'task_id' => $task->id,
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
            'requester_id' => $requester->id,
            'reason' => 'Confirm the output.',
            'status' => CheckpointStatus::OPEN,
            'opened_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($requester)
            ->getJson(route('internal.requests.monitoring', $request))
            ->assertOk()
            ->assertJsonPath('current_stage', 'Validation')
            ->assertJsonPath('validations.open', 1)
            ->assertJsonPath('action.label', 'Open validation')
            ->assertJsonPath('stages.5.state', 'attention');
    }

    public function test_project_monitoring_shows_both_approvals_and_remains_private(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $otherRequester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Supplier Portal', 'description' => null, 'color' => '#4f46e5',
            'status' => ProjectStatus::PLANNED->value, 'start_date' => null, 'due_date' => null,
        ]);
        $request = ProjectRequest::create([
            'workspace_id' => $workspace->id,
            'requester_id' => $requester->id,
            'title' => 'Supplier Portal',
            'benefit' => 'Suppliers can answer routine questions without email.',
            'concept' => 'A self-service supplier portal for daily requests.',
            'business_process' => 'Requests currently move between inboxes by hand.',
            'flow' => 'Supplier submits, the team reviews, and the result is recorded.',
            'status' => ProjectRequestStatus::PENDING_MANAGER,
            'meeting_held_at' => now()->subDay(),
            'spv_id' => $owner->id,
            'spv_at' => now()->subHours(2),
            'project_id' => $project->id,
        ]);

        $this->actingAs($requester)
            ->get(route('desk.project-requests.show', $request))
            ->assertOk()
            ->assertSee('Supervisor approval')
            ->assertSee('Manager approval');

        $this->actingAs($requester)
            ->getJson(route('internal.project-requests.monitoring', $request))
            ->assertOk()
            ->assertJsonPath('current_stage', 'Manager approval')
            ->assertJsonPath('stages.2.state', 'completed')
            ->assertJsonPath('stages.3.state', 'current');

        $this->actingAs($otherRequester)
            ->getJson(route('internal.project-requests.monitoring', $request))
            ->assertForbidden();
    }

    private function featureSystem(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Inventory Core', 'description' => null, 'color' => '#8b5cf6', 'pic_id' => $owner->id,
        ]);

        return [$workspace, $owner, $system, $this->member($workspace, WorkspaceRole::REQUESTER)];
    }

    private function featureRequest(Workspace $workspace, $system, User $requester, Feature $feature, User $assignee): FeatureRequest
    {
        $request = FeatureRequest::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'requester_id' => $requester->id,
            'title' => 'Export the monthly stock report',
            'problem' => 'The report is assembled manually and takes too long every month.',
            'desired_outcome' => 'A secure export matching the finance spreadsheet.',
            'benefit' => 'Saves about two staff days each month and reduces transcription errors.',
            'urgency' => RequestUrgency::NORMAL,
            'status' => FeatureRequestStatus::IN_PROGRESS,
            'feature_id' => $feature->id,
        ]);
        $request->forceFill([
            'assignee_id' => $assignee->id,
            'scheduled_start' => now()->startOfDay(),
            'scheduled_due' => now()->addWeek()->startOfDay(),
        ])->save();

        return $request;
    }

    private function task(FeatureRequest $request, int $statusId, User $creator, string $title): Task
    {
        return Task::create([
            'workspace_id' => $request->workspace_id,
            'project_id' => $request->project_id,
            'feature_id' => $request->feature_id,
            'status_id' => $statusId,
            'creator_id' => $creator->id,
            'title' => $title,
            'priority' => TaskPriority::MEDIUM,
            'estimate_minutes' => 120,
            'position' => Task::where('feature_id', $request->feature_id)->count() * 1024 + 1024,
        ]);
    }

    private function member(Workspace $workspace, WorkspaceRole $role): User
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
}
