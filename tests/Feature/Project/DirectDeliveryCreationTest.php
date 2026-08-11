<?php

namespace Tests\Feature\Project;

use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\GenerateTaskBreakdown;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DirectDeliveryCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_it_member_can_create_a_project_and_request_an_ai_plan(): void
    {
        Queue::fake();
        [$workspace] = $this->workspace();
        $member = $this->member($workspace, WorkspaceRole::MEMBER);

        $this->actingAs($member)->postJson(route('internal.projects.store', $workspace), [
            'name' => 'Network refresh',
            'description' => 'Replace the aging office network equipment, migrate every floor safely, document the final topology, and verify connectivity without disrupting daily operations.',
            'color' => '#4f46e5',
            'status' => ProjectStatus::PLANNED->value,
            'start_date' => '2026-08-17',
            'due_date' => '2026-09-30',
            'generate_with_ai' => true,
        ])->assertCreated();

        $project = Project::where('name', 'Network refresh')->firstOrFail();
        $this->assertTrue($project->memberships()->where('user_id', $member->id)->exists());
        Queue::assertPushed(GenerateTaskBreakdown::class);
    }

    public function test_an_it_member_can_create_a_feature_directly_and_request_an_ai_plan(): void
    {
        Queue::fake();
        [$workspace, $owner] = $this->workspace();
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'ERP Core',
            'description' => 'Company resource planning system.',
            'color' => '#0ea5e9',
            'pic_id' => $owner->id,
        ]);
        $member = $this->member($workspace, WorkspaceRole::MEMBER);

        $this->actingAs($member)
            ->get(route('app.features.create', $workspace))
            ->assertOk()
            ->assertSee('ERP Core');

        $this->actingAs($member)->postJson(route('internal.features.store', $workspace), [
            'system_public_id' => $system->public_id,
            'name' => 'Bulk invoice export',
            'description' => 'Let finance select multiple approved invoices, export them into one structured file, preserve the existing column format, and clearly report any invoice that cannot be exported.',
            'starts_at' => '2026-08-17',
            'due_at' => '2026-08-28',
            'generate_with_ai' => true,
        ])->assertCreated();

        $feature = Feature::where('name', 'Bulk invoice export')->firstOrFail();
        $this->assertSame($system->id, $feature->project_id);
        $this->assertTrue($system->memberships()->where('user_id', $member->id)->exists());
        Queue::assertPushed(GenerateTaskBreakdown::class);
    }

    public function test_a_manual_task_can_be_added_to_an_existing_feature_but_not_to_a_foreign_system_feature(): void
    {
        [$workspace, $owner] = $this->workspace();
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'ERP Core',
            'description' => 'Company resource planning system.',
            'color' => '#0ea5e9',
            'pic_id' => $owner->id,
        ]);
        $feature = Feature::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'name' => 'Invoice export',
            'description' => 'Export approved invoices for finance.',
        ]);
        $status = $system->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        $this->actingAs($owner)
            ->get(route('app.features.show', [$workspace, $system]))
            ->assertOk()
            ->assertSee('Add task');

        $this->actingAs($owner)->postJson(route('internal.tasks.store', $system), [
            'title' => 'Build invoice export query',
            'description' => 'Return only approved invoices selected by finance.',
            'status_public_id' => $status->public_id,
            'feature_public_id' => $feature->public_id,
            'priority' => TaskPriority::MEDIUM->value,
            'assignee_public_ids' => [],
        ])->assertCreated();

        $this->assertSame($feature->id, Task::firstOrFail()->feature_id);

        $otherSystem = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Warehouse Core',
            'description' => 'Warehouse inventory system.',
            'color' => '#8b5cf6',
            'pic_id' => $owner->id,
        ]);
        $foreignFeature = Feature::create([
            'workspace_id' => $workspace->id,
            'project_id' => $otherSystem->id,
            'name' => 'Stock export',
            'description' => 'Export current warehouse stock.',
        ]);

        $this->actingAs($owner)->postJson(route('internal.tasks.store', $system), [
            'title' => 'Invalid cross-system task',
            'status_public_id' => $status->public_id,
            'feature_public_id' => $foreignFeature->public_id,
            'priority' => TaskPriority::MEDIUM->value,
            'assignee_public_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('feature_public_id');
    }

    public function test_ai_creation_requires_a_detailed_description_but_manual_creation_does_not(): void
    {
        Queue::fake();
        [$workspace, $owner] = $this->workspace();
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'ERP Core',
            'description' => 'Company resource planning system.',
            'color' => '#0ea5e9',
            'pic_id' => $owner->id,
        ]);

        $this->actingAs($owner)->postJson(route('internal.projects.store', $workspace), [
            'name' => 'Short AI project',
            'description' => 'Too short for AI.',
            'status' => ProjectStatus::PLANNED->value,
            'generate_with_ai' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('description');

        $this->actingAs($owner)->postJson(route('internal.features.store', $workspace), [
            'system_public_id' => $system->public_id,
            'name' => 'Short AI feature',
            'description' => 'Too short for AI.',
            'generate_with_ai' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('description');

        $this->actingAs($owner)->postJson(route('internal.projects.store', $workspace), [
            'name' => 'Manual project',
            'status' => ProjectStatus::PLANNED->value,
        ])->assertCreated();

        Queue::assertNothingPushed();
    }

    public function test_a_viewer_cannot_create_projects_or_features(): void
    {
        Queue::fake();
        [$workspace, $owner] = $this->workspace();
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'ERP Core',
            'description' => 'Company resource planning system.',
            'color' => '#0ea5e9',
            'pic_id' => $owner->id,
        ]);
        $viewer = $this->member($workspace, WorkspaceRole::VIEWER);

        $this->actingAs($viewer)->postJson(route('internal.projects.store', $workspace), [
            'name' => 'Not allowed',
            'status' => ProjectStatus::PLANNED->value,
        ])->assertForbidden();

        $this->actingAs($viewer)->postJson(route('internal.features.store', $workspace), [
            'system_public_id' => $system->public_id,
            'name' => 'Not allowed',
            'description' => 'This viewer must not create feature work directly.',
        ])->assertForbidden();

        Queue::assertNothingPushed();
    }

    /** @return array{Workspace, User} */
    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'ITD',
            'timezone' => 'UTC',
            'locale' => 'en',
            'week_start' => 1,
        ]);

        return [$workspace, $owner];
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
