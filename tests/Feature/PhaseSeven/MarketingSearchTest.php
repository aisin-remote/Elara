<?php

namespace Tests\Feature\PhaseSeven;

use App\Actions\Project\CreateProject;
use App\Actions\Project\CreateSystem;
use App\Actions\Task\CreateTask;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ConversationType;
use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectRequestStatus;
use App\Enums\ProjectStatus;
use App\Enums\RequestUrgency;
use App\Enums\SupportingTaskCategory;
use App\Enums\SupportingTaskStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskPropertyType;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Conversation;
use App\Models\Feature;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectRequest;
use App\Models\SupportingTask;
use App\Models\TaskProperty;
use App\Models\TaskPropertyValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketingSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_marketing_and_legal_pages_are_public(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertSee('Make progress visible')
            ->assertSee('Task Management')
            ->assertSee('How it works')
            ->assertSee('Slack')
            ->assertSee('Unlimited access')
            ->assertSee('No package limits')
            ->assertSee('Frequently asked questions')
            ->assertSee('Get started');

        $this->get(route('legal.privacy'))->assertOk()->assertSee('Privacy notice');
        $this->get(route('legal.terms'))->assertOk()->assertSee('Terms of use');
        $this->get(route('legal.accessibility'))->assertOk()->assertSee('Accessibility statement');
        $this->get('/missing-orbitra-page')->assertNotFound()->assertSee('We could not find that page');
    }

    public function test_sensitive_boundaries_have_explicit_rate_limits(): void
    {
        foreach ([
            'internal.invitations.store' => 'throttle:10,1',
            'internal.messages.store' => 'throttle:60,1',
            'internal.files.store' => 'throttle:20,1',
            'internal.task-attachments.store' => 'throttle:20,1',
            'app.search' => 'throttle:30,1',
            'password.store' => 'throttle:6,1',
        ] as $routeName => $middleware) {
            $this->assertContains($middleware, app('router')->getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    public function test_global_search_returns_every_accessible_resource_and_hides_other_workspaces(): void
    {
        Storage::fake('local');
        [$owner, $workspace, $project] = $this->project('Atlas Project');
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        app(CreateTask::class)->handle($project, $owner, [
            'title' => 'Atlas task', 'description' => null, 'status_public_id' => $status->public_id,
            'category_public_id' => null, 'priority' => TaskPriority::HIGH->value, 'start_at' => null,
            'due_at' => null, 'estimate_minutes' => null, 'assignee_public_ids' => [],
        ]);
        $member = User::factory()->create(['first_name' => 'Atlas', 'last_name' => 'Member', 'email' => 'atlas@example.com']);
        $workspace->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::MEMBER, 'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now()]);
        ProjectFile::create([
            'workspace_id' => $workspace->id, 'project_id' => $project->id, 'uploader_id' => $owner->id,
            'disk' => 'local', 'path' => 'tests/atlas-brief.txt', 'original_name' => 'atlas-brief.txt', 'mime_type' => 'text/plain', 'size' => 10,
        ]);
        $conversation = Conversation::create([
            'workspace_id' => $workspace->id, 'project_id' => $project->id, 'type' => ConversationType::PROJECT,
            'title' => 'Atlas launch room', 'created_by' => $owner->id,
        ]);
        $conversation->participants()->attach($owner->id, ['joined_at' => now()]);

        [, , $hiddenProject] = $this->project('Atlas Secret Project');

        $this->actingAs($owner)->get(route('app.workspaces.show', $workspace))
            ->assertOk()
            ->assertSee('data-global-search-dialog', false)
            ->assertSee('data-search-quick-routes', false)
            ->assertSee('Quick access')
            ->assertSee(route('app.tasks.index', $workspace), false)
            ->assertSee(route('app.projects.index', $workspace), false)
            ->assertSee(route('app.features.index', $workspace), false)
            ->assertSee(route('app.supporting.index', $workspace), false)
            ->assertSee(route('app.schedule.index', $workspace), false)
            ->assertSee('aria-label="Open global search"', false)
            ->assertSee('Ctrl K')
            ->assertDontSee('Search workspace…');

        $this->actingAs($owner)->getJson(route('app.search', [$workspace, 'q' => 'Atlas']))
            ->assertOk()
            ->assertJsonPath('total', 5)
            ->assertJsonCount(5, 'results')
            ->assertJsonFragment(['type' => 'task', 'description' => 'Task in Atlas Project'])
            ->assertJsonFragment(['type' => 'member', 'description' => 'atlas@example.com'])
            ->assertJsonMissing(['label' => $hiddenProject->name]);

        $this->actingAs($owner)->get(route('app.search', [$workspace, 'q' => 'Atlas']))
            ->assertOk()
            ->assertSee('5 results')
            ->assertSee('Task in Atlas Project')
            ->assertSee('Private file · text/plain')
            ->assertSee('atlas@example.com')
            ->assertSee('Project conversation')
            ->assertSee('<mark', false)
            ->assertDontSee($hiddenProject->name);
    }

    public function test_global_search_validates_length_paginates_and_requires_membership(): void
    {
        [$owner, $workspace] = $this->project('Orbitra Search 00');
        foreach (range(1, 15) as $index) {
            Project::create([
                'workspace_id' => $workspace->id, 'owner_id' => $owner->id,
                'name' => sprintf('Orbitra Search %02d', $index), 'description' => null, 'color' => '#2eb0fb',
                'status' => ProjectStatus::ACTIVE, 'start_date' => null, 'due_date' => null,
            ]);
        }

        $this->actingAs($owner)->get(route('app.search', [$workspace, 'q' => 'O']))->assertSessionHasErrors('q');
        $this->actingAs($owner)->get(route('app.search', [$workspace, 'q' => 'Orbitra', 'page' => 2]))
            ->assertOk()->assertSee('Page 2 of 2');
        $this->actingAs(User::factory()->create())->get(route('app.search', [$workspace, 'q' => 'Orbitra']))->assertNotFound();
    }

    public function test_global_search_includes_features_requests_supporting_and_custom_property_values(): void
    {
        [$owner, $workspace, $project] = $this->project('Search Coverage');
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Operations Hub',
            'description' => null,
            'color' => '#8b5cf6',
            'pic_id' => $owner->id,
        ]);
        $feature = Feature::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'name' => 'Nebula dashboard',
            'description' => 'A consolidated operational dashboard.',
        ]);
        $featureRequest = FeatureRequest::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'requester_id' => $owner->id,
            'title' => 'Nebula export request',
            'problem' => 'Reports must currently be exported one at a time.',
            'desired_outcome' => 'Users can export the consolidated report.',
            'benefit' => 'Saves repetitive administrative work.',
            'urgency' => RequestUrgency::NORMAL,
            'status' => FeatureRequestStatus::PENDING_REVIEW,
        ]);
        $projectRequest = ProjectRequest::create([
            'workspace_id' => $workspace->id,
            'requester_id' => $owner->id,
            'title' => 'Nebula rollout proposal',
            'benefit' => 'Makes rollout progress visible.',
            'concept' => 'A structured rollout workspace.',
            'business_process' => 'Rollout updates are currently shared by email.',
            'flow' => 'Plan, execute, and validate.',
            'status' => ProjectRequestStatus::PENDING_MEETING,
        ]);
        SupportingTask::create([
            'workspace_id' => $workspace->id,
            'creator_id' => $owner->id,
            'assignee_id' => $owner->id,
            'title' => 'Nebula printer setup',
            'description' => 'Configure the meeting-room printer.',
            'category' => SupportingTaskCategory::HARDWARE,
            'priority' => TaskPriority::LOW,
            'status' => SupportingTaskStatus::TODO,
        ]);

        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $task = app(CreateTask::class)->handle($project, $owner, [
            'title' => 'Plain task title',
            'description' => null,
            'status_public_id' => $status->public_id,
            'category_public_id' => null,
            'priority' => TaskPriority::MEDIUM->value,
            'start_at' => null,
            'due_at' => null,
            'estimate_minutes' => null,
            'assignee_public_ids' => [],
        ]);
        $property = TaskProperty::create([
            'project_id' => $project->id,
            'name' => 'Client reference',
            'type' => TaskPropertyType::TEXT,
            'position' => 1024,
        ]);
        TaskPropertyValue::create([
            'task_property_id' => $property->id,
            'task_id' => $task->id,
            'value_json' => 'Nebula-2048',
        ]);

        $this->actingAs($owner)->getJson(route('app.search', [$workspace, 'q' => 'Nebula']))
            ->assertOk()
            ->assertJsonPath('total', 5)
            ->assertJsonCount(5, 'results')
            ->assertJsonFragment(['type' => 'feature', 'label' => $feature->name, 'description' => 'Feature in Operations Hub'])
            ->assertJsonFragment(['type' => 'feature request', 'label' => $featureRequest->title, 'description' => 'Feature request for Operations Hub'])
            ->assertJsonFragment(['type' => 'project request', 'label' => $projectRequest->title, 'description' => 'Project request · Pending Meeting'])
            ->assertJsonFragment(['type' => 'supporting', 'label' => 'Nebula printer setup', 'description' => 'Supporting task · Todo'])
            ->assertJsonFragment(['type' => 'task', 'label' => $task->title, 'description' => 'Task in Search Coverage']);

        $this->actingAs($owner)->get(route('app.search', [$workspace, 'q' => 'Nebula']))
            ->assertOk()
            ->assertSee('5 results')
            ->assertSee('Feature in Operations Hub')
            ->assertSee('Feature request for Operations Hub')
            ->assertSee('Project request · Pending Meeting')
            ->assertSee('Supporting task · Todo')
            ->assertSee('Plain task title');
    }

    public function test_team_filters_workload_and_permission_aware_member_details_work(): void
    {
        [$owner, $workspace, $project] = $this->project('Team Delivery');
        $member = User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Designer', 'last_seen_at' => now()]);
        $membership = $workspace->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::MEMBER, 'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now()]);
        $project->memberships()->create(['user_id' => $member->id, 'role' => ProjectMemberRole::MEMBER]);
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        app(CreateTask::class)->handle($project, $owner, [
            'title' => 'Accessible workload item', 'description' => null, 'status_public_id' => $status->public_id,
            'category_public_id' => null, 'priority' => TaskPriority::MEDIUM->value, 'start_at' => null,
            'due_at' => now()->addDay()->toDateTimeString(), 'estimate_minutes' => null, 'assignee_public_ids' => [$member->public_id],
        ]);

        $this->actingAs($owner)->get(route('app.workspaces.team', [$workspace, 'role' => 'member', 'presence' => 'active', 'project' => $project->public_id]))
            ->assertOk()->assertSee('Nadia Designer')->assertSee('Workload')->assertSee('1 tasks');
        $this->actingAs($owner)->get(route('app.workspaces.team.show', [$workspace, $membership]))
            ->assertOk()->assertSee('Member details')->assertSee('Accessible workload item')->assertSee('Team Delivery');
        $this->actingAs(User::factory()->create())->get(route('app.workspaces.team.show', [$workspace, $membership]))->assertNotFound();
    }

    public function test_department_team_only_shows_the_members_current_active_department(): void
    {
        [$owner, $itdWorkspace] = $this->project('ITD Delivery');
        $itdWorkspace->update([
            'organization_department_id' => 20,
            'organization_department_code' => 'ITD',
        ]);
        $qauWorkspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => "QAU's Workspace",
            'timezone' => 'UTC',
            'locale' => 'en',
            'week_start' => 1,
        ]);
        $qauWorkspace->update([
            'organization_department_id' => 30,
            'organization_department_code' => 'QAU',
        ]);

        $aldino = User::factory()->create(['first_name' => 'Aldino', 'last_name' => 'Saputra']);
        $oldMembership = $itdWorkspace->memberships()->create([
            'user_id' => $aldino->id,
            'role' => WorkspaceRole::REQUESTER,
            'status' => WorkspaceMemberStatus::INACTIVE,
            'joined_at' => now(),
        ]);
        $qauWorkspace->memberships()->create([
            'user_id' => $aldino->id,
            'role' => WorkspaceRole::REQUESTER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('app.workspaces.team', $itdWorkspace))
            ->assertOk()
            ->assertDontSee('Aldino Saputra');
        $this->actingAs($owner)->get(route('app.workspaces.team.show', [$itdWorkspace, $oldMembership]))
            ->assertNotFound();
        $this->actingAs($owner)->get(route('app.workspaces.team', $qauWorkspace))
            ->assertOk()
            ->assertSee('Aldino Saputra');
    }

    public function test_team_ignores_an_orphaned_membership_after_a_manual_user_delete(): void
    {
        [$owner, $workspace] = $this->project('Safe Team');
        $workspace->update([
            'organization_department_id' => 20,
            'organization_department_code' => 'ITD',
        ]);
        $deleted = User::factory()->create(['first_name' => 'Deleted', 'last_name' => 'Person']);
        $membership = $workspace->memberships()->create([
            'user_id' => $deleted->id,
            'role' => WorkspaceRole::MEMBER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        DB::statement('PRAGMA foreign_keys = OFF');
        DB::table('users')->where('id', $deleted->id)->delete();
        DB::statement('PRAGMA foreign_keys = ON');

        $this->assertDatabaseHas('workspace_members', ['id' => $membership->id]);
        $this->actingAs($owner)->get(route('app.workspaces.team', $workspace))
            ->assertOk()
            ->assertDontSee('Deleted Person');
        $this->actingAs($owner)->get(route('app.workspaces.team.show', [$workspace, $membership]))
            ->assertNotFound();
    }

    private function project(string $name): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, ['name' => fake()->company(), 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1]);
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => $name, 'description' => null, 'color' => '#2eb0fb',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => null, 'due_date' => null,
        ]);

        return [$owner, $workspace, $project];
    }
}
