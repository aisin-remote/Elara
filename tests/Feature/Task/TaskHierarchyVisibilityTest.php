<?php

namespace Tests\Feature\Task;

use App\Actions\Project\CreateProject;
use App\Actions\Task\CreateTask;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OrganizationDirectory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TaskHierarchyVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'organization.required' => true,
            'organization.jit_auth' => false,
            'organization.connection' => 'organization',
            'database.connections.organization' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('organization');
        $this->createOrganizationSchema();
    }

    public function test_each_level_only_resolves_itself_and_lower_levels_in_its_branch(): void
    {
        [$workspace, , $people] = $this->hierarchy();
        $directory = app(OrganizationDirectory::class);

        $this->assertEqualsCanonicalizing(
            ['Group Manager', 'Department Manager', 'Section Supervisor', 'Primary Staff', 'Other Staff', 'Line Operator'],
            $directory->taskMembers($people['gm'], $workspace)->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['Department Manager', 'Section Supervisor', 'Primary Staff', 'Other Staff', 'Line Operator'],
            $directory->taskMembers($people['manager'], $workspace)->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['Section Supervisor', 'Primary Staff', 'Line Operator'],
            $directory->taskMembers($people['supervisor'], $workspace)->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['Primary Staff', 'Line Operator'],
            $directory->taskMembers($people['staff'], $workspace)->pluck('name')->all(),
        );
        $this->assertSame(
            ['Line Operator'],
            $directory->taskMembers($people['operator'], $workspace)->pluck('name')->all(),
        );
    }

    public function test_task_menu_defaults_to_my_tasks_and_rejects_superior_or_peer_selection(): void
    {
        [$workspace, $project, $people, $tasks] = $this->hierarchy();
        $manager = $people['manager'];

        $this->actingAs($manager)->get(route('app.tasks.index', $workspace))
            ->assertOk()
            ->assertSee('My task database')
            ->assertDontSee($tasks['manager']->title)
            ->assertDontSee($tasks['supervisor']->title)
            ->assertSee(route('app.tasks.index', ['workspace' => $workspace, 'assignee' => $people['supervisor']->public_id]), false)
            ->assertDontSee(route('app.tasks.index', ['workspace' => $workspace, 'assignee' => $people['gm']->public_id]), false);

        $this->actingAs($manager)->get(route('app.tasks.index', [
            'workspace' => $workspace,
            'view' => 'assigned',
        ]))
            ->assertOk()
            ->assertSee($tasks['manager']->title)
            ->assertDontSee($tasks['supervisor']->title);

        $this->actingAs($manager)->get(route('app.tasks.index', [
            'workspace' => $workspace,
            'assignee' => $people['supervisor']->public_id,
        ]))
            ->assertOk()
            ->assertSee("Section Supervisor's tasks")
            ->assertSee($tasks['supervisor']->title)
            ->assertDontSee($tasks['manager']->title);

        $this->actingAs($manager)->get(route('app.tasks.index', [
            'workspace' => $workspace,
            'assignee' => $people['gm']->public_id,
        ]))->assertNotFound();
        $this->actingAs($manager)->get(route('app.tasks.show', $tasks['gm']))->assertNotFound();

        $this->actingAs($manager)->get(route('app.projects.tasks', [$workspace, $project]))
            ->assertOk()
            ->assertSee($tasks['manager']->title)
            ->assertSee($tasks['supervisor']->title)
            ->assertSee($tasks['operator']->title)
            ->assertDontSee($tasks['gm']->title);

        $this->actingAs($people['staff'])->get(route('app.tasks.index', $workspace))
            ->assertOk()
            ->assertSee(route('app.tasks.index', ['workspace' => $workspace, 'assignee' => $people['operator']->public_id]), false)
            ->assertDontSee(route('app.tasks.index', ['workspace' => $workspace, 'assignee' => $people['supervisor']->public_id]), false)
            ->assertDontSee(route('app.tasks.index', ['workspace' => $workspace, 'assignee' => $people['other_staff']->public_id]), false);
    }

    /** @return array{Workspace, Project, array<string, User>, array<string, Task>} */
    private function hierarchy(): array
    {
        $people = [
            'gm' => $this->user('Group', 'Manager'),
            'manager' => $this->user('Department', 'Manager'),
            'supervisor' => $this->user('Section', 'Supervisor'),
            'staff' => $this->user('Primary', 'Staff'),
            'other_staff' => $this->user('Other', 'Staff'),
            'operator' => $this->user('Line', 'Operator'),
        ];
        $workspace = app(CreateWorkspace::class)->handle($people['gm'], [
            'name' => "ITD's Workspace",
            'timezone' => 'Asia/Jakarta',
            'locale' => 'en',
            'week_start' => 1,
        ]);
        $workspace->update(['organization_department_id' => 10, 'organization_department_code' => 'ITD']);

        foreach ($people as $key => $user) {
            if ($key !== 'gm') {
                $workspace->memberships()->create([
                    'user_id' => $user->id,
                    'role' => match ($key) {
                        'manager' => WorkspaceRole::MANAGER,
                        'supervisor' => WorkspaceRole::SUPERVISOR,
                        default => WorkspaceRole::MEMBER,
                    },
                    'status' => WorkspaceMemberStatus::ACTIVE,
                    'joined_at' => now(),
                ]);
            }
        }

        $this->organizationUser($people['gm'], 'GM');
        $this->organizationUser($people['manager'], 'MGR');
        $this->organizationUser($people['supervisor'], 'SPV', 100);
        $this->organizationUser($people['staff'], 'STF', 100);
        $this->organizationUser($people['other_staff'], 'LDR', 101);
        $this->organizationUser($people['operator'], 'OP', 100);

        $project = app(CreateProject::class)->handle($workspace, $people['gm'], [
            'name' => 'Hierarchy Project',
            'description' => 'Task visibility fixture.',
            'color' => '#4f46e5',
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => null,
            'due_date' => null,
        ]);
        foreach ($people as $key => $user) {
            if ($key !== 'gm') {
                $project->memberships()->create([
                    'user_id' => $user->id,
                    'role' => $key === 'manager' ? ProjectMemberRole::MANAGER : ProjectMemberRole::MEMBER,
                ]);
            }
        }

        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $tasks = collect($people)->mapWithKeys(fn (User $user, string $key) => [
            $key => app(CreateTask::class)->handle($project, $people['gm'], [
                'title' => $user->name.' work',
                'description' => 'Hierarchy visibility task.',
                'status_public_id' => $status->public_id,
                'category_public_id' => null,
                'priority' => TaskPriority::MEDIUM->value,
                'start_at' => null,
                'due_at' => null,
                'estimate_minutes' => null,
                'assignee_public_ids' => [$user->public_id],
            ]),
        ])->all();

        return [$workspace, $project, $people, $tasks];
    }

    private function user(string $firstName, string $lastName): User
    {
        return User::factory()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower($firstName.'.'.$lastName).'@example.com',
        ]);
    }

    private function organizationUser(User $user, string $rankCode, ?int $sectionId = null): void
    {
        $connection = DB::connection('organization');
        $organizationUserId = $connection->table('users')->insertGetId([
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $connection->table('model_has_job_ranks')->insert([
            'model_id' => $organizationUserId,
            'job_rank_id' => $connection->table('job_ranks')->where('code', $rankCode)->value('id'),
        ]);
        $connection->table('model_has_departments')->insert([
            'model_id' => $organizationUserId,
            'department_id' => 10,
        ]);
        if ($sectionId) {
            $connection->table('model_has_sections')->insert([
                'model_id' => $organizationUserId,
                'section_id' => $sectionId,
            ]);
        }

        $user->update(['organization_user_id' => $organizationUserId]);
    }

    private function createOrganizationSchema(): void
    {
        $schema = Schema::connection('organization');
        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
        });
        $schema->create('job_ranks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code');
        });
        $schema->create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code');
        });
        $schema->create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_id');
            $table->string('name');
            $table->string('code');
        });
        $schema->create('sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id');
            $table->string('name');
        });
        foreach (['job_ranks' => 'job_rank_id', 'departments' => 'department_id', 'sections' => 'section_id'] as $table => $foreignKey) {
            $schema->create('model_has_'.$table, function (Blueprint $blueprint) use ($foreignKey): void {
                $blueprint->unsignedBigInteger('model_id');
                $blueprint->unsignedBigInteger($foreignKey);
            });
        }

        $connection = DB::connection('organization');
        $connection->table('divisions')->insert(['id' => 1, 'name' => 'Engineering', 'code' => 'ENG']);
        $connection->table('departments')->insert(['id' => 10, 'division_id' => 1, 'name' => 'Information Technology', 'code' => 'ITD']);
        $connection->table('sections')->insert([
            ['id' => 100, 'department_id' => 10, 'name' => 'Information System'],
            ['id' => 101, 'department_id' => 10, 'name' => 'Infrastructure'],
        ]);
        foreach (['GM', 'MGR', 'SPV', 'LDR', 'STF', 'OP'] as $code) {
            $connection->table('job_ranks')->insert(['name' => $code, 'code' => $code]);
        }
    }
}
