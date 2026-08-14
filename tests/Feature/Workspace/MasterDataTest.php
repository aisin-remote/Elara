<?php

namespace Tests\Feature\Workspace;

use App\Actions\Project\CreateProject;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\SupportArticle;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskStatusTemplate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_pages_are_admin_only(): void
    {
        [$owner, $workspace] = $this->workspace();

        foreach (['app.settings.master', 'app.settings.master.categories', 'app.settings.master.status-templates', 'app.settings.master.articles'] as $routeName) {
            $this->actingAs($owner)->get(route($routeName, $workspace))->assertOk();
        }
        $this->actingAs($owner)->get(route('app.settings.master.status-templates', $workspace))
            ->assertSee('Workflow')
            ->assertDontSee('Workflow defaults')
            ->assertDontSee('Status template');
        $this->actingAs($owner)->get(route('app.settings.master', $workspace))
            ->assertSee('Workflow')
            ->assertSee(route('app.settings.master.status-templates', $workspace), false)
            ->assertDontSee('Holidays')
            ->assertDontSee(route('app.settings.master.holidays', $workspace), false);

        foreach ([WorkspaceRole::MANAGER, WorkspaceRole::SUPERVISOR, WorkspaceRole::MEMBER, WorkspaceRole::VIEWER] as $role) {
            $this->actingAs($this->member($workspace, $role))
                ->get(route('app.settings.master.categories', $workspace))
                ->assertForbidden();
        }

        // A requester never reaches the delivery desk at all (PRD-01 gate).
        $this->actingAs($this->member($workspace, WorkspaceRole::REQUESTER))
            ->get(route('app.settings.master.categories', $workspace))
            ->assertForbidden();
    }

    public function test_every_contributing_itd_role_has_full_settings_access(): void
    {
        [$owner, $workspace] = $this->workspace();
        $workspace->update([
            'organization_department_id' => 20,
            'organization_department_code' => 'ITD',
        ]);

        foreach ([WorkspaceRole::MANAGER, WorkspaceRole::SUPERVISOR, WorkspaceRole::MEMBER] as $role) {
            $user = $this->member($workspace, $role);

            $this->actingAs($user)->get(route('app.settings.profile', $workspace))
                ->assertOk()
                ->assertSee('Workspace')
                ->assertSee('Master data')
                ->assertSee('Integrations');
            $this->actingAs($user)->get(route('app.workspaces.settings', $workspace))->assertOk();
            $this->actingAs($user)->get(route('app.settings.master', $workspace))->assertOk();
            $this->actingAs($user)->get(route('app.settings.integrations', $workspace))->assertOk();
        }

        $member = $workspace->memberships()->where('role', WorkspaceRole::MEMBER->value)->firstOrFail()->user;
        $this->actingAs($member)->patchJson(route('internal.workspaces.update', $workspace), [
            'name' => "ITD's Workspace",
            'icon' => null,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'week_start' => 1,
        ])->assertOk();
        $this->actingAs($member)->postJson(route('internal.master.status-templates.store', $workspace), [
            'name' => 'IT Review',
            'color' => '#6366f1',
            'category' => TaskStatusCategory::TODO->value,
        ])->assertCreated();

        $this->assertSame("ITD's Workspace", $workspace->fresh()->name);
        $this->assertDatabaseHas('task_status_templates', ['workspace_id' => $workspace->id, 'name' => 'IT Review']);
        $this->assertFalse($this->member($workspace, WorkspaceRole::VIEWER)->can('manageSettings', $workspace));
    }

    public function test_category_in_use_cannot_be_archived_without_saying_where_tasks_go(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $doomed = $workspace->taskCategories()->create(['name' => 'Legacy', 'color' => '#6366f1']);
        $keeper = $workspace->taskCategories()->create(['name' => 'Support', 'color' => '#10b981']);
        $task = $this->task($project, $owner, $doomed);

        $this->actingAs($owner)
            ->postJson(route('internal.master.categories.archive', $doomed))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('replacement_public_id');

        $this->assertNull($doomed->fresh()->archived_at);

        $this->actingAs($owner)
            ->postJson(route('internal.master.categories.archive', $doomed), ['replacement_public_id' => $keeper->public_id])
            ->assertOk();

        $this->assertNotNull($doomed->fresh()->archived_at);
        $this->assertSame($keeper->id, $task->fresh()->category_id);
    }

    public function test_unused_category_archives_and_restores_cleanly(): void
    {
        [$owner, $workspace] = $this->workspace();
        $category = $workspace->taskCategories()->create(['name' => 'Unused', 'color' => '#6366f1']);

        $this->actingAs($owner)->postJson(route('internal.master.categories.archive', $category))->assertOk();
        $this->assertNotNull($category->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'task_category.archived']);

        $this->actingAs($owner)->postJson(route('internal.master.categories.restore', $category))->assertOk();
        $this->assertNull($category->fresh()->archived_at);
    }

    public function test_category_rename_is_unique_per_workspace_and_denied_for_members(): void
    {
        [$owner, $workspace] = $this->workspace();
        $first = $workspace->taskCategories()->create(['name' => 'Billing', 'color' => '#6366f1']);
        $second = $workspace->taskCategories()->create(['name' => 'Support', 'color' => '#10b981']);

        $this->actingAs($owner)
            ->patchJson(route('internal.master.categories.update', $second), ['name' => 'Billing', 'color' => '#10b981'])
            ->assertUnprocessable();

        $this->actingAs($this->member($workspace, WorkspaceRole::MEMBER))
            ->patchJson(route('internal.master.categories.update', $first), ['name' => 'Hijacked', 'color' => '#10b981'])
            ->assertForbidden();

        $this->assertSame('Billing', $first->fresh()->name);
    }

    public function test_status_template_replaces_the_built_in_set_for_new_projects(): void
    {
        [$owner, $workspace] = $this->workspace();

        // Without a template the built-in four still apply.
        $before = app(CreateProject::class)->handle($workspace, $owner, $this->projectAttributes('Before'));
        $this->assertSame(['Outstanding', 'In Progress', 'Pending', 'Done'], $before->taskStatuses()->orderBy('position')->pluck('name')->all());
        $this->assertSame(TaskStatusCategory::COMPLETED, $before->taskStatuses()->where('name', 'Done')->firstOrFail()->category);

        foreach ([['Intake', TaskStatusCategory::TODO], ['Build', TaskStatusCategory::IN_PROGRESS], ['Shipped', TaskStatusCategory::COMPLETED]] as [$name, $category]) {
            $this->actingAs($owner)->postJson(route('internal.master.status-templates.store', $workspace), [
                'name' => $name, 'color' => '#6366f1', 'category' => $category->value,
            ])->assertCreated();
        }

        $after = app(CreateProject::class)->handle($workspace, $owner, $this->projectAttributes('After'));
        $this->assertSame(['Intake', 'Build', 'Shipped'], $after->taskStatuses()->orderBy('position')->pluck('name')->all());
        $this->assertSame(['Outstanding', 'In Progress', 'Pending', 'Done'], $before->fresh()->taskStatuses()->orderBy('position')->pluck('name')->all());
    }

    public function test_archived_status_template_stops_reaching_new_projects(): void
    {
        [$owner, $workspace] = $this->workspace();
        $this->actingAs($owner)->postJson(route('internal.master.status-templates.store', $workspace), [
            'name' => 'Intake', 'color' => '#6366f1', 'category' => TaskStatusCategory::TODO->value,
        ])->assertCreated();

        $template = TaskStatusTemplate::firstOrFail();
        $this->actingAs($owner)->postJson(route('internal.master.status-templates.archive', $template))->assertOk();

        // Archiving the last template falls back to the built-in set rather than shipping
        // a project with no statuses at all.
        $project = app(CreateProject::class)->handle($workspace, $owner, $this->projectAttributes('Fallback'));
        $this->assertSame(['Outstanding', 'In Progress', 'Pending', 'Done'], $project->taskStatuses()->orderBy('position')->pluck('name')->all());
    }

    public function test_legacy_default_statuses_are_upgraded_without_touching_custom_groups(): void
    {
        [, , $project] = $this->project();
        $legacy = [
            'Outstanding' => ['To Do', TaskStatusCategory::TODO, 2048],
            'In Progress' => ['In Progress', TaskStatusCategory::IN_PROGRESS, 3072],
            'Pending' => ['Backlog', TaskStatusCategory::BACKLOG, 1024],
            'Done' => ['Completed', TaskStatusCategory::COMPLETED, 4096],
        ];

        foreach ($legacy as $current => [$name, $category, $position]) {
            $project->taskStatuses()->where('name', $current)->firstOrFail()->update(compact('name', 'category', 'position'));
        }
        $project->taskStatuses()->whereIn('name', ['Backlog', 'In Progress', 'Completed'])->update(['archived_at' => now()]);
        $project->taskStatuses()->create([
            'name' => 'Feature', 'color' => '#8b5cf6', 'category' => TaskStatusCategory::TODO,
            'position' => 5120, 'is_system' => false,
        ]);

        $migration = require database_path('migrations/2026_08_12_120000_replace_default_task_statuses.php');
        $migration->up();

        $this->assertSame(
            ['Outstanding', 'In Progress', 'Pending', 'Done', 'Feature'],
            $project->taskStatuses()->active()->pluck('name')->all(),
        );

        $migration->down();

        $this->assertSame(
            ['Backlog', 'To Do', 'In Progress', 'Completed', 'Feature'],
            $project->taskStatuses()->active()->pluck('name')->all(),
        );
    }

    public function test_articles_are_editable_and_archiving_removes_them_from_the_help_centre(): void
    {
        [$owner] = $this->workspace();

        $this->actingAs($owner)->postJson(route('internal.master.articles.store'), [
            'title' => 'Requesting a new feature', 'category' => 'Requests',
            'body' => 'Open the desk and describe the outcome you need.', 'is_published' => true,
        ])->assertCreated();

        $article = SupportArticle::firstOrFail();
        $this->assertSame('requesting-a-new-feature', $article->slug, 'A blank slug is derived from the title.');
        $this->assertTrue(SupportArticle::published()->whereKey($article->id)->exists());

        $this->actingAs($owner)->patchJson(route('internal.master.articles.update', $article), [
            'title' => 'Requesting a feature', 'category' => 'Requests', 'body' => 'Updated body.', 'slug' => $article->slug,
        ])->assertOk();
        $this->assertFalse($article->fresh()->is_published, 'An unchecked publish box unpublishes.');

        $this->actingAs($owner)->postJson(route('internal.master.articles.archive', $article))->assertOk();
        $this->assertFalse(SupportArticle::published()->whereKey($article->id)->exists());
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
        $project = app(CreateProject::class)->handle($workspace, $owner, $this->projectAttributes('Inventory'));

        return [$owner, $workspace, $project];
    }

    private function projectAttributes(string $name): array
    {
        return [
            'name' => $name, 'description' => null, 'color' => '#2eb0fb',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => null, 'due_date' => null,
        ];
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

    private function task($project, User $creator, TaskCategory $category): Task
    {
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        return $project->tasks()->create([
            'workspace_id' => $project->workspace_id,
            'status_id' => $status->id,
            'category_id' => $category->id,
            'creator_id' => $creator->id,
            'title' => 'Categorised work',
            'priority' => TaskPriority::MEDIUM,
            'position' => 1024,
        ]);
    }
}
