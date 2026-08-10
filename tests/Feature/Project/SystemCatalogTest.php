<?php

namespace Tests\Feature\Project;

use App\Actions\Project\CreateProject;
use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_system_never_appears_on_a_surface_that_says_projects(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $system = $this->system($workspace, $owner, 'Inventory Core');

        // Index, archive list, and the create page's "already exists" panel.
        $this->actingAs($owner)->get(route('app.projects.index', $workspace))
            ->assertOk()->assertSee($project->name)->assertDontSee($system->name);

        $this->actingAs($owner)->get(route('app.projects.create', $workspace))
            ->assertOk()->assertSee($project->name)->assertDontSee($system->name);

        // Sidebar quick access is shared by every delivery page, and the projects index
        // above renders it — so it is already covered there. The dashboard deliberately is
        // not asserted here: it names the system in Recent activity, which is correct.

        // Global search.
        $this->actingAs($owner)->get(route('app.search', [$workspace, 'q' => 'Inventory']))
            ->assertOk()->assertDontSee($system->name);
    }

    public function test_the_dashboard_timeline_plots_projects_but_not_systems(): void
    {
        [$owner, $workspace] = $this->workspace();

        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Website Redesign', 'description' => null, 'color' => '#2eb0fb',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => now()->subDay()->toDateString(), 'due_date' => now()->addWeek()->toDateString(),
        ]);
        $system = $this->system($workspace, $owner, 'Payroll');
        $system->update(['start_date' => now()->subDay(), 'due_date' => now()->addWeek()]);

        $this->actingAs($owner)->getJson(route('internal.dashboard.index', $workspace))
            ->assertOk()
            ->assertJsonFragment(['public_id' => $project->public_id])
            ->assertJsonMissing(['public_id' => $system->public_id]);
    }

    public function test_feature_menu_lists_systems_with_their_features_and_loose_tasks(): void
    {
        [$owner, $workspace] = $this->workspace();
        $owner->update(['first_name' => 'Bagas', 'last_name' => 'Nugroho']);
        $system = $this->system($workspace, $owner, 'Inventory Core');

        $feature = Feature::create([
            'workspace_id' => $workspace->id, 'project_id' => $system->id,
            'name' => 'Bulk export', 'status' => 'scheduled',
        ]);
        $this->task($system, $owner, 'Design the export screen', $feature);
        $this->task($system, $owner, 'Rotate expired certificates');

        $this->actingAs($owner)->get(route('app.features.index', $workspace))
            ->assertOk()
            ->assertSee('Inventory Core')
            ->assertSee('title="Bagas Nugroho"', false);

        $this->actingAs($owner)->get(route('app.features.show', [$workspace, $system]))
            ->assertOk()
            ->assertSee('Bulk export')
            ->assertSee('Design the export screen')
            ->assertSee('Maintenance tasks')
            ->assertSee('Rotate expired certificates');
    }

    public function test_a_delivery_project_cannot_be_opened_as_a_system(): void
    {
        [$owner, $workspace, $project] = $this->project();

        $this->actingAs($owner)->get(route('app.features.show', [$workspace, $project]))->assertNotFound();
    }

    public function test_creating_a_system_seeds_statuses_and_records_the_pic(): void
    {
        [$owner, $workspace] = $this->workspace();
        $pic = $this->member($workspace, WorkspaceRole::MEMBER);

        $this->actingAs($owner)->postJson(route('internal.master.systems.store', $workspace), [
            'name' => 'Payroll', 'color' => '#2eb0fb', 'description' => 'Monthly payroll runs.',
            'pic_public_id' => $pic->public_id,
        ])->assertCreated();

        $system = Project::where('name', 'Payroll')->firstOrFail();
        $this->assertSame(ProjectType::SYSTEM, $system->type);
        $this->assertSame($pic->id, $system->pic()->id);
        $this->assertSame(4, $system->taskStatuses()->count(), 'A system gets the same starting statuses as a project.');
    }

    public function test_a_requester_cannot_be_made_pic(): void
    {
        [$owner, $workspace] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        $this->actingAs($owner)->postJson(route('internal.master.systems.store', $workspace), [
            'name' => 'Payroll', 'color' => '#2eb0fb', 'pic_public_id' => $requester->public_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('pic_public_id');
    }

    public function test_changing_the_pic_demotes_the_previous_holder(): void
    {
        [$owner, $workspace] = $this->workspace();
        $first = $this->member($workspace, WorkspaceRole::MEMBER);
        $second = $this->member($workspace, WorkspaceRole::MEMBER);
        $system = $this->system($workspace, $owner, 'Payroll', $first);

        $this->actingAs($owner)->patchJson(route('internal.master.systems.update', $system), [
            'name' => 'Payroll', 'color' => '#2eb0fb', 'pic_public_id' => $second->public_id,
        ])->assertOk();

        $this->assertSame($second->id, $system->fresh()->pic()->id);
        $this->assertSame(
            ProjectMemberRole::MEMBER,
            $system->memberships()->where('user_id', $first->id)->first()->role,
            'The old PIC stays on the system, but no longer as its manager.'
        );
    }

    public function test_a_system_with_live_features_cannot_be_archived(): void
    {
        [$owner, $workspace] = $this->workspace();
        $system = $this->system($workspace, $owner, 'Payroll');
        $feature = Feature::create([
            'workspace_id' => $workspace->id, 'project_id' => $system->id,
            'name' => 'Bulk export', 'status' => 'scheduled',
        ]);

        $this->actingAs($owner)->postJson(route('internal.master.systems.archive', $system))
            ->assertUnprocessable()->assertJsonValidationErrors('system');
        $this->assertNull($system->fresh()->archived_at);

        $feature->update(['archived_at' => now()]);
        $this->actingAs($owner)->postJson(route('internal.master.systems.archive', $system))->assertOk();
        $this->assertNotNull($system->fresh()->archived_at);
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);

        return [$owner, $workspace];
    }

    private function project(): array
    {
        [$owner, $workspace] = $this->workspace();
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Website Redesign', 'description' => null, 'color' => '#2eb0fb',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => null, 'due_date' => null,
        ]);

        return [$owner, $workspace, $project];
    }

    private function system(Workspace $workspace, User $actor, string $name, ?User $pic = null): Project
    {
        return app(CreateSystem::class)->handle($workspace, $actor, [
            'name' => $name, 'description' => null, 'color' => '#8b5cf6',
            'pic_id' => ($pic ?? $actor)->id,
        ]);
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

    private function task(Project $project, User $creator, string $title, ?Feature $feature = null): Task
    {
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        return $project->tasks()->create([
            'workspace_id' => $project->workspace_id,
            'feature_id' => $feature?->id,
            'status_id' => $status->id,
            'creator_id' => $creator->id,
            'title' => $title,
            'priority' => TaskPriority::MEDIUM,
            'position' => 1024,
        ]);
    }
}
