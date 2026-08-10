<?php

namespace Tests\Feature\Workspace;

use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\CheckpointStatus;
use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectRequestStatus;
use App\Enums\RequestUrgency;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Feature;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\ValidationCheckpoint;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Two requesters in one workspace must not see each other's work. The list scopes are one
 * half of that; a direct URL is the half that actually gets tried.
 */
class RequesterIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_the_desk_lists_only_my_own_requests(): void
    {
        [$workspace, , $system] = $this->workspace();
        $mine = $this->member($workspace, WorkspaceRole::REQUESTER);
        $theirs = $this->member($workspace, WorkspaceRole::REQUESTER);

        $this->featureRequest($workspace, $system, $mine, 'My stock export');
        $this->featureRequest($workspace, $system, $theirs, 'Their payroll tweak');
        $this->projectRequest($workspace, $mine, 'My supplier portal');
        $this->projectRequest($workspace, $theirs, 'Their warehouse scanner');

        // One tab per type now, so each is checked on its own — and each must also be clean
        // of the other requester's work, not merely missing it because of the split.
        $this->actingAs($mine)
            ->get(route('desk.index', ['tab' => 'feature']))
            ->assertOk()
            ->assertSee('My stock export')
            ->assertDontSee('Their payroll tweak')
            ->assertDontSee('Their warehouse scanner');

        $this->actingAs($mine)
            ->get(route('desk.index', ['tab' => 'project']))
            ->assertOk()
            ->assertSee('My supplier portal')
            ->assertDontSee('Their warehouse scanner')
            ->assertDontSee('Their payroll tweak');
    }

    public function test_a_direct_link_to_someone_elses_request_is_refused(): void
    {
        [$workspace, , $system] = $this->workspace();
        $mine = $this->member($workspace, WorkspaceRole::REQUESTER);
        $theirs = $this->member($workspace, WorkspaceRole::REQUESTER);

        $feature = $this->featureRequest($workspace, $system, $theirs, 'Their payroll tweak');
        $project = $this->projectRequest($workspace, $theirs, 'Their warehouse scanner');

        $this->actingAs($mine)->get(route('desk.requests.show', $feature))->assertForbidden();
        $this->actingAs($mine)->get(route('desk.project-requests.show', $project))->assertForbidden();
    }

    public function test_i_cannot_withdraw_or_resubmit_what_is_not_mine(): void
    {
        [$workspace, , $system] = $this->workspace();
        $mine = $this->member($workspace, WorkspaceRole::REQUESTER);
        $theirs = $this->member($workspace, WorkspaceRole::REQUESTER);

        $feature = $this->featureRequest($workspace, $system, $theirs, 'Their payroll tweak');

        $this->actingAs($mine)->post(route('desk.requests.withdraw', $feature))->assertForbidden();
        $this->actingAs($mine)->post(route('desk.requests.resubmit', $feature))->assertForbidden();

        $this->assertSame(FeatureRequestStatus::PENDING_REVIEW, $feature->fresh()->status);
    }

    public function test_validations_are_scoped_and_cannot_be_answered_for_someone_else(): void
    {
        [$workspace, $pic, $system] = $this->workspace();
        $mine = $this->member($workspace, WorkspaceRole::REQUESTER);
        $theirs = $this->member($workspace, WorkspaceRole::REQUESTER);

        $checkpoint = $this->checkpoint($workspace, $system, $theirs, 'Their download button');

        $this->actingAs($mine)
            ->get(route('desk.validations.index'))
            ->assertOk()
            ->assertDontSee('Their download button');

        $this->actingAs($mine)
            ->post(route('desk.validations.respond', $checkpoint), ['decision' => 'approved'])
            ->assertForbidden();

        $this->assertSame(CheckpointStatus::OPEN, $checkpoint->fresh()->status);
    }

    public function test_a_requester_from_another_workspace_gets_a_404_not_a_403(): void
    {
        [$workspaceA, , $systemA] = $this->workspace();
        $outsider = $this->member($this->workspace()[0], WorkspaceRole::REQUESTER);
        $inside = $this->member($workspaceA, WorkspaceRole::REQUESTER);

        $feature = $this->featureRequest($workspaceA, $systemA, $inside, 'Inside request');

        // Route binding refuses to resolve it at all, so its existence is not confirmed.
        $this->actingAs($outsider)->get(route('desk.requests.show', $feature))->assertNotFound();
    }

    /** @return array{0: Workspace, 1: User, 2: Project} */
    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Inventory Core', 'description' => null, 'color' => '#8b5cf6', 'pic_id' => $owner->id,
        ]);

        return [$workspace, $owner, $system];
    }

    private function member(Workspace $workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $user->id, 'role' => $role,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);

        return $user;
    }

    private function featureRequest(Workspace $workspace, Project $system, User $requester, string $title): FeatureRequest
    {
        return FeatureRequest::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'requester_id' => $requester->id,
            'title' => $title,
            'problem' => 'The current process is manual and takes far longer than anyone budgeted for.',
            'desired_outcome' => 'The same output, produced by the system rather than by hand.',
            'urgency' => RequestUrgency::NORMAL,
            'status' => FeatureRequestStatus::PENDING_REVIEW,
        ]);
    }

    private function projectRequest(Workspace $workspace, User $requester, string $title): ProjectRequest
    {
        return ProjectRequest::create([
            'workspace_id' => $workspace->id,
            'requester_id' => $requester->id,
            'title' => $title,
            'benefit' => 'People answer the same questions by email all day.',
            'concept' => 'A portal that answers them without a person.',
            'business_process' => 'Today the work is manual and passed on by email.',
            'flow' => 'Raised → checked → approved → recorded.',
            'status' => ProjectRequestStatus::PENDING_MEETING,
        ]);
    }

    private function checkpoint(Workspace $workspace, Project $system, User $requester, string $taskTitle): ValidationCheckpoint
    {
        $feature = Feature::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'name' => 'Export the monthly stock report',
        ]);

        $request = $this->featureRequest($workspace, $system, $requester, 'Export the monthly stock report');
        $request->forceFill(['feature_id' => $feature->id, 'status' => FeatureRequestStatus::IN_PROGRESS])->save();

        $status = $system->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $task = Task::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'feature_id' => $feature->id,
            'status_id' => $status->id,
            'creator_id' => $requester->id,
            'title' => $taskTitle,
            'priority' => TaskPriority::MEDIUM,
            'estimate_minutes' => 120,
            'position' => 1024,
        ]);

        return ValidationCheckpoint::create([
            'workspace_id' => $workspace->id,
            'task_id' => $task->id,
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
            'requester_id' => $requester->id,
            'status' => CheckpointStatus::OPEN,
            'opened_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }
}
