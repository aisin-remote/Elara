<?php

namespace Tests\Feature\Request;

use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectRequestStatus;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\OrbitraNotification;
use App\Services\OrganizationDirectory;
use Database\Seeders\OrganizationDemoSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private int $nextOrganizationUserId = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'organization.required' => true,
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

    public function test_staff_feature_needs_department_approval_and_is_visible_only_inside_the_department(): void
    {
        Notification::fake();
        [, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $head = $this->member($workspace, WorkspaceRole::REQUESTER);
        $colleague = $this->member($workspace, WorkspaceRole::REQUESTER);
        $outsider = $this->member($workspace, WorkspaceRole::REQUESTER);
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);

        $this->organizationUser($requester, 'STF', 10);
        $this->organizationUser($head, 'MGR', 10);
        $this->organizationUser($colleague, 'LDR', 10);
        $this->organizationUser($outsider, 'STF', 11);

        $this->actingAs($requester)->post(route('desk.requests.store', $workspace), $this->featurePayload($system))->assertRedirect();

        $feature = FeatureRequest::firstOrFail();
        $this->assertSame(FeatureRequestStatus::PENDING_DEPARTMENT, $feature->status);
        $this->assertSame(10, $feature->requester_department_external_id);
        $this->assertSame('STF', $feature->requester_job_rank_code);
        Notification::assertSentTo($head, OrbitraNotification::class);
        Notification::assertNotSentTo($supervisor, OrbitraNotification::class);

        $this->actingAs($colleague)->get(route('desk.index'))->assertOk()->assertSee($feature->title);
        $this->actingAs($colleague)->get(route('desk.requests.show', $feature))->assertOk();
        $this->actingAs($outsider)->get(route('desk.index'))->assertOk()->assertDontSee($feature->title);
        $this->actingAs($outsider)->get(route('desk.requests.show', $feature))->assertForbidden();

        $this->actingAs($head)->get(route('desk.department-approvals.index', $workspace))
            ->assertOk()->assertSee($feature->title)->assertSee('Department approvals');
        $this->actingAs($head)->post(route('desk.department-approvals.features.decide', [$workspace, $feature]), [
            'decision' => 'approve',
        ])->assertRedirect(route('desk.department-approvals.index', $workspace));

        $feature->refresh();
        $this->assertSame(FeatureRequestStatus::PENDING_REVIEW, $feature->status);
        $this->assertSame($head->id, $feature->department_reviewed_by);
        Notification::assertSentTo($supervisor, OrbitraNotification::class);
        $this->actingAs($requester)->get(route('desk.requests.show', $feature))
            ->assertOk()->assertSee('Department approval');
    }

    public function test_non_it_manager_is_mapped_to_requester_and_bypasses_department_approval(): void
    {
        [, $workspace, $system] = $this->system();
        $manager = $this->member($workspace, WorkspaceRole::MEMBER);
        $this->organizationUser($manager, 'COOR', 10);

        $this->post(route('login'), ['email' => $manager->email, 'password' => 'password'])
            ->assertRedirect('/desk');

        $this->assertSame(
            WorkspaceRole::REQUESTER,
            $manager->workspaceMemberships()->where('workspace_id', $workspace->id)->firstOrFail()->role
        );

        $this->post(route('desk.requests.store', $workspace), $this->featurePayload($system))->assertRedirect();
        $this->assertSame(FeatureRequestStatus::PENDING_REVIEW, FeatureRequest::firstOrFail()->status);
    }

    public function test_project_moves_from_department_approval_to_the_existing_itd_chain(): void
    {
        Notification::fake();
        [, $workspace] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $head = $this->member($workspace, WorkspaceRole::REQUESTER);
        $colleague = $this->member($workspace, WorkspaceRole::REQUESTER);
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);

        $this->organizationUser($requester, 'SN STF', 10);
        $this->organizationUser($head, 'MGR', 10);
        $this->organizationUser($colleague, 'STF', 10);

        $this->actingAs($requester)->post(route('desk.project-requests.store', $workspace), $this->projectPayload())->assertRedirect();
        $project = ProjectRequest::firstOrFail();
        $this->assertSame(ProjectRequestStatus::PENDING_DEPARTMENT, $project->status);
        Notification::assertSentTo($head, OrbitraNotification::class);
        Notification::assertNotSentTo($supervisor, OrbitraNotification::class);

        $this->actingAs($colleague)->get(route('desk.project-requests.show', $project))->assertForbidden();
        $this->actingAs($head)->post(route('desk.department-approvals.projects.decide', [$workspace, $project]), [
            'decision' => 'approve',
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame(ProjectRequestStatus::PENDING_MEETING, $project->status);
        $this->assertSame($head->id, $project->department_reviewed_by);
        Notification::assertSentTo($supervisor, OrbitraNotification::class);
        $this->actingAs($head)->get(route('desk.project-requests.show', $project))->assertOk();
        $this->actingAs($requester)->get(route('desk.project-requests.show', $project))
            ->assertOk()->assertSee('Department approval')->assertSee('Scoping meeting');
    }

    public function test_information_requested_by_department_returns_to_the_same_stage(): void
    {
        [, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $head = $this->member($workspace, WorkspaceRole::REQUESTER);
        $this->organizationUser($requester, 'STF', 10);
        $this->organizationUser($head, 'MGR', 10);

        $this->actingAs($requester)->post(route('desk.requests.store', $workspace), $this->featurePayload($system));
        $feature = FeatureRequest::firstOrFail();

        $this->actingAs($head)->post(route('desk.department-approvals.features.decide', [$workspace, $feature]), [
            'decision' => 'needs_info',
            'note' => 'Tambahkan dampak proses dan frekuensi penggunaan.',
        ])->assertRedirect();

        $feature->refresh();
        $this->assertSame(FeatureRequestStatus::NEEDS_INFO, $feature->status);
        $this->assertSame('department', $feature->needs_info_stage);

        $this->actingAs($requester)->post(route('desk.requests.resubmit', $feature), [
            'problem' => 'The monthly stock report is assembled manually every week and often contains errors.',
            'desired_outcome' => 'A verified export that finance can use every week without copying columns manually.',
        ])->assertRedirect();

        $this->assertSame(FeatureRequestStatus::PENDING_DEPARTMENT, $feature->fresh()->status);
    }

    public function test_unmapped_user_cannot_bypass_the_organization_chain(): void
    {
        [, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        $this->actingAs($requester)->post(route('desk.requests.store', $workspace), $this->featurePayload($system))
            ->assertSessionHasErrors('organization');

        $this->assertSame(0, FeatureRequest::count());
    }

    public function test_staff_request_is_not_queued_without_an_active_department_approver(): void
    {
        [, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $this->organizationUser($requester, 'STF', 10);

        $this->actingAs($requester)->post(route('desk.requests.store', $workspace), $this->featurePayload($system))
            ->assertSessionHasErrors('organization');
        $this->actingAs($requester)->post(route('desk.project-requests.store', $workspace), $this->projectPayload())
            ->assertSessionHasErrors('organization');

        $this->assertSame(0, FeatureRequest::count());
        $this->assertSame(0, ProjectRequest::count());
    }

    public function test_organization_demo_seeder_creates_idempotent_directory_profiles(): void
    {
        $this->app->instance('env', 'local');

        $this->seed(OrganizationDemoSeeder::class);
        $this->seed(OrganizationDemoSeeder::class);

        $this->assertSame(5, DB::connection('organization')->table('users')->count());
        $this->assertSame(5, DB::connection('organization')->table('model_has_job_ranks')->count());
        $this->assertSame(5, DB::connection('organization')->table('model_has_departments')->count());

        $requester = User::factory()->create(['email' => 'requester@example.com']);
        $head = User::factory()->create(['email' => 'department-head@example.com']);

        $this->assertSame('STF', app(OrganizationDirectory::class)->profile($requester)['rank_code']);
        $this->assertSame('FIN', app(OrganizationDirectory::class)->profile($head)['department_code']);
        $this->assertSame('MGR', app(OrganizationDirectory::class)->profile($head)['rank_code']);
    }

    public function test_itd_rank_groups_map_to_the_delivery_roles_while_owner_stays_owner(): void
    {
        [$owner, $workspace] = $this->workspace();
        $itdSupervisor = $this->member($workspace, WorkspaceRole::REQUESTER);
        $itdManager = $this->member($workspace, WorkspaceRole::MEMBER);
        $this->organizationUser($owner, 'STF', 10);
        $this->organizationUser($itdSupervisor, 'SCH', 20);
        $this->organizationUser($itdManager, 'MGR', 20);

        $directory = app(OrganizationDirectory::class);
        $directory->syncMembershipRoles($owner);
        $directory->syncMembershipRoles($itdSupervisor);
        $directory->syncMembershipRoles($itdManager);

        $this->assertSame(WorkspaceRole::OWNER, $owner->workspaceMemberships()->firstOrFail()->role);
        $this->assertSame(WorkspaceRole::SUPERVISOR, $itdSupervisor->workspaceMemberships()->firstOrFail()->role);
        $this->assertSame(WorkspaceRole::MANAGER, $itdManager->workspaceMemberships()->firstOrFail()->role);
    }

    private function createOrganizationSchema(): void
    {
        $schema = Schema::connection('organization');
        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('npk')->nullable();
            $table->string('password')->nullable();
            $table->string('company')->nullable();
            $table->timestamps();
        });
        $schema->create('job_ranks', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('code');
            $table->string('name');
        });
        $schema->create('divisions', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('code');
            $table->string('name');
        });
        $schema->create('departments', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('division_id');
            $table->string('code');
            $table->string('name');
        });
        $schema->create('sections', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('department_id');
            $table->string('name');
        });
        foreach ([
            'model_has_job_ranks' => 'job_rank_id',
            'model_has_departments' => 'department_id',
            'model_has_sections' => 'section_id',
        ] as $tableName => $foreignKey) {
            $schema->create($tableName, function (Blueprint $table) use ($foreignKey): void {
                $table->unsignedBigInteger('model_id');
                $table->unsignedBigInteger($foreignKey);
            });
        }

        DB::connection('organization')->table('job_ranks')->insert([
            ['id' => 1, 'code' => 'MGR', 'name' => 'Manager'],
            ['id' => 2, 'code' => 'COOR', 'name' => 'Coordinator'],
            ['id' => 3, 'code' => 'SPV', 'name' => 'Supervisor'],
            ['id' => 4, 'code' => 'SCH', 'name' => 'Section Head'],
            ['id' => 5, 'code' => 'LDR', 'name' => 'Leader'],
            ['id' => 6, 'code' => 'STF', 'name' => 'Staff'],
            ['id' => 7, 'code' => 'SN STF', 'name' => 'Senior Staff'],
        ]);
        DB::connection('organization')->table('divisions')->insert([
            ['id' => 1, 'code' => 'CORP', 'name' => 'Corporate'],
            ['id' => 2, 'code' => 'TECH', 'name' => 'Technology'],
        ]);
        DB::connection('organization')->table('departments')->insert([
            ['id' => 10, 'division_id' => 1, 'code' => 'HRD', 'name' => 'Human Resources Development'],
            ['id' => 11, 'division_id' => 1, 'code' => 'FIN', 'name' => 'Finance'],
            ['id' => 20, 'division_id' => 2, 'code' => 'ITD', 'name' => 'Information Technology Development'],
        ]);
    }

    private function organizationUser(User $user, string $rankCode, int $departmentId): void
    {
        $connection = DB::connection('organization');
        $id = $this->nextOrganizationUserId++;
        $rankId = $connection->table('job_ranks')->where('code', $rankCode)->value('id');
        $connection->table('users')->insert(['id' => $id, 'email' => $user->email]);
        $connection->table('model_has_job_ranks')->insert(['model_id' => $id, 'job_rank_id' => $rankId]);
        $connection->table('model_has_departments')->insert(['model_id' => $id, 'department_id' => $departmentId]);
    }

    /** @return array{User, Workspace} */
    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);

        return [$owner, $workspace];
    }

    /** @return array{User, Workspace, Project} */
    private function system(): array
    {
        [$owner, $workspace] = $this->workspace();
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Inventory Core', 'description' => null, 'color' => '#8b5cf6', 'pic_id' => $owner->id,
        ]);

        return [$owner, $workspace, $system];
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

    private function featurePayload(Project $system): array
    {
        return [
            'system_public_id' => $system->public_id,
            'title' => 'Export the monthly stock report',
            'problem' => 'We copy the numbers into a spreadsheet by hand every month and it takes two days.',
            'desired_outcome' => 'A download button that produces the same columns we already use.',
            'urgency' => 'normal',
        ];
    }

    private function projectPayload(): array
    {
        return [
            'title' => 'Supplier self-service portal',
            'background' => 'Procurement staff answer supplier delivery questions by phone and email throughout the day.',
            'why_needed' => 'The manual process consumes about fifteen hours each week and gives suppliers inconsistent answers.',
            'objectives' => [
                ['title' => 'Reduce routine calls', 'description' => 'Cut delivery-status calls by at least seventy percent.'],
            ],
            'illustration' => 'A secure supplier portal reads open purchase orders and records delivery-date confirmations.',
            'before_state' => 'A supplier calls procurement, procurement checks the ERP, and then sends an email confirmation.',
            'after_state' => 'A supplier signs in, sees open orders, and confirms or proposes a delivery date online.',
            'benefits' => ['Save approximately fifteen staff hours each week.'],
            'cost_items' => ['Portal design, development, and supplier onboarding.'],
            'roi' => 'The saved staff time should recover the implementation cost within the first year of operation.',
        ];
    }
}
