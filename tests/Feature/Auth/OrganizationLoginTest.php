<?php

namespace Tests\Feature\Auth;

use App\Actions\Project\CreateSystem;
use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectRequestStatus;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationLoginTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.organization', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('organization.connection', 'organization');
        config()->set('organization.required', true);
        config()->set('organization.jit_auth', true);
        config()->set('organization.it_department_code', 'ITD');
        DB::purge('organization');

        $this->createOrganizationSchema();

        $owner = User::factory()->create(['password' => 'LocalOwner!123']);
        $this->workspace = Workspace::factory()->for($owner, 'owner')->create();
        $this->workspace->memberships()->create([
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        config()->set('organization.workspace_public_id', $this->workspace->public_id);
    }

    public function test_first_company_login_provisions_a_requester_without_a_remember_token(): void
    {
        $organizationId = $this->addOrganizationUser('New Requester', 'new.requester@example.com', 'STF', 'FIN');

        $this->post(route('login'), [
            'email' => 'NEW.REQUESTER@EXAMPLE.COM',
            'password' => 'CompanyPass!123',
            'remember' => '1',
        ])->assertRedirect('/desk');

        $user = User::where('email', 'new.requester@example.com')->firstOrFail();
        $departmentWorkspace = Workspace::where('organization_department_code', 'FIN')->firstOrFail();
        $membership = $departmentWorkspace->memberships()->where('user_id', $user->id)->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->isOrganizationManaged());
        $this->assertSame($organizationId, $user->organization_user_id);
        $this->assertSame("FIN's Workspace", $departmentWorkspace->name);
        $this->assertSame(WorkspaceRole::REQUESTER, $membership->role);
        $this->assertDatabaseMissing('workspace_members', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
            'status' => WorkspaceMemberStatus::ACTIVE->value,
        ]);
        $this->assertNull($user->remember_token);
        $this->assertFalse(Hash::check('CompanyPass!123', $user->password));
    }

    public function test_itd_supervisor_is_provisioned_into_the_delivery_workspace(): void
    {
        $this->addOrganizationUser('IT Supervisor', 'it.supervisor@example.com', 'SPV', 'ITD');

        $this->post(route('login'), [
            'email' => 'it.supervisor@example.com',
            'password' => 'CompanyPass!123',
        ])->assertRedirect(route('app.dashboard'));

        $user = User::where('email', 'it.supervisor@example.com')->firstOrFail();
        $this->assertSame("ITD's Workspace", $this->workspace->fresh()->name);
        $this->assertSame('ITD', $this->workspace->fresh()->organization_department_code);
        $this->assertSame(
            WorkspaceRole::SUPERVISOR,
            $this->workspace->memberships()->where('user_id', $user->id)->firstOrFail()->role,
        );
    }

    public function test_department_workspace_routes_its_request_to_itd_delivery_and_approval_back_to_the_department(): void
    {
        Notification::fake();
        $this->addOrganizationUser('Finance Requester', 'finance.requester@example.com', 'STF', 'FIN');
        $this->addOrganizationUser('Finance Manager', 'finance.manager@example.com', 'MGR', 'FIN');
        $this->addOrganizationUser('IT Supervisor', 'it.approver@example.com', 'SPV', 'ITD');

        foreach (['finance.requester@example.com', 'finance.manager@example.com', 'it.approver@example.com'] as $email) {
            $this->post(route('login'), ['email' => $email, 'password' => 'CompanyPass!123'])->assertRedirect();
            $this->post(route('logout'));
        }

        $financeWorkspace = Workspace::where('organization_department_code', 'FIN')->firstOrFail();
        $deliveryWorkspace = $this->workspace->fresh();
        $requester = User::where('email', 'finance.requester@example.com')->firstOrFail();
        $manager = User::where('email', 'finance.manager@example.com')->firstOrFail();
        $supervisor = User::where('email', 'it.approver@example.com')->firstOrFail();
        $system = app(CreateSystem::class)->handle($deliveryWorkspace, $deliveryWorkspace->owner, [
            'name' => 'Inventory Core',
            'description' => null,
            'color' => '#8b5cf6',
            'pic_id' => $deliveryWorkspace->owner_id,
        ]);

        $this->actingAs($requester)->post(route('desk.requests.store', $financeWorkspace), [
            'system_public_id' => $system->public_id,
            'title' => 'Export the monthly stock report',
            'problem' => 'We copy the numbers into a spreadsheet by hand every month and it takes two days.',
            'desired_outcome' => 'A download button that produces the same columns we already use.',
            'urgency' => 'normal',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $feature = FeatureRequest::firstOrFail();
        $this->assertSame($deliveryWorkspace->id, $feature->workspace_id);
        $this->assertSame(FeatureRequestStatus::PENDING_DEPARTMENT, $feature->status);
        $this->actingAs($manager)->get(route('desk.department-approvals.index', $financeWorkspace))
            ->assertOk()
            ->assertSee($feature->title);
        $this->actingAs($manager)->get(route('desk.requests.show', $feature))
            ->assertOk()
            ->assertSee(route('desk.department-approvals.features.decide', [$financeWorkspace, $feature]), false);
        $this->actingAs($manager)->post(route('desk.department-approvals.features.decide', [$financeWorkspace, $feature]), [
            'decision' => 'approve',
        ])->assertRedirect(route('desk.department-approvals.index', $financeWorkspace));

        $this->assertSame(FeatureRequestStatus::PENDING_REVIEW, $feature->fresh()->status);
        $this->actingAs($supervisor)->get(route('app.approvals.show', [$deliveryWorkspace, $feature]))->assertOk();

        $this->actingAs($requester)->post(route('desk.project-requests.store', $financeWorkspace), [
            'title' => 'Supplier self-service portal',
            'benefit' => 'Suppliers phone us for delivery dates, which costs the team about fifteen hours a week.',
            'concept' => 'A website suppliers sign into to see open purchase orders and confirm delivery dates.',
            'business_process' => 'Today a supplier calls procurement, procurement checks the ERP, then emails a confirmation.',
            'flow' => 'Supplier signs in, sees open orders, confirms or proposes a date, procurement is notified.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $project = ProjectRequest::firstOrFail();
        $this->assertSame($deliveryWorkspace->id, $project->workspace_id);
        $this->assertSame(ProjectRequestStatus::PENDING_DEPARTMENT, $project->status);
        $this->actingAs($manager)->post(route('desk.department-approvals.projects.decide', [$financeWorkspace, $project]), [
            'decision' => 'approve',
        ])->assertRedirect(route('desk.department-approvals.index', $financeWorkspace));
        $this->assertSame(ProjectRequestStatus::PENDING_MEETING, $project->fresh()->status);
        $this->actingAs($supervisor)->get(route('app.approvals.projects.show', [$deliveryWorkspace, $project]))->assertOk();

        $this->assertSame(1, $requester->workspaceMemberships()->active()->count());
        $this->assertSame($financeWorkspace->id, $requester->workspaceMemberships()->active()->firstOrFail()->workspace_id);
        $this->assertSame($financeWorkspace->id, $manager->workspaceMemberships()->active()->firstOrFail()->workspace_id);
        $this->assertSame($deliveryWorkspace->id, $supervisor->workspaceMemberships()->active()->firstOrFail()->workspace_id);
    }

    public function test_invalid_company_password_does_not_create_an_orbitra_user(): void
    {
        $this->addOrganizationUser('New Requester', 'invalid.password@example.com', 'STF', 'FIN');

        $this->post(route('login'), [
            'email' => 'invalid.password@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'invalid.password@example.com']);
    }

    public function test_deleting_a_directory_user_purges_their_orbitra_data_and_logs_them_out(): void
    {
        $organizationId = $this->addOrganizationUser('Former Requester', 'former.requester@example.com', 'STF', 'FIN');

        $this->post(route('login'), [
            'email' => 'former.requester@example.com',
            'password' => 'CompanyPass!123',
        ])->assertRedirect('/desk');

        $user = User::where('email', 'former.requester@example.com')->firstOrFail();
        ActivityLog::record($user->workspaceMemberships()->firstOrFail()->workspace, $user, 'test.user.activity');

        $this->get(route('desk.index'))->assertOk();
        DB::connection('organization')->table('users')->where('id', $organizationId)->delete();

        $this->get(route('desk.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('workspace_members', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('activity_logs', [
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
        ]);
    }

    public function test_company_password_can_confirm_identity_but_cannot_be_changed_or_reset_in_orbitra(): void
    {
        $organizationId = $this->addOrganizationUser('Company User', 'company.user@example.com', 'STF', 'ITD');
        $user = User::factory()->create([
            'email' => 'company.user@example.com',
            'password' => 'UnusedLocal!123',
            'auth_source' => 'organization',
            'organization_user_id' => $organizationId,
        ]);
        $this->workspace->memberships()->create([
            'user_id' => $user->id,
            'role' => WorkspaceRole::MEMBER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('password.confirm'), ['password' => 'CompanyPass!123'])
            ->assertRedirect(route('app.dashboard'));

        $this->actingAs($user)
            ->put(route('internal.settings.password.update'), [
                'current_password' => 'CompanyPass!123',
                'password' => 'DifferentPass!456',
                'password_confirmation' => 'DifferentPass!456',
            ])->assertSessionHasErrors(['password']);

        Notification::fake();
        $this->post(route('logout'));
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', trans('passwords.sent'));
        Notification::assertNothingSent();
    }

    public function test_owner_with_a_matching_directory_email_stays_on_local_authentication(): void
    {
        $owner = $this->workspace->owner;
        $this->addOrganizationUser('Workspace Owner', $owner->email, 'MGR', 'ITD');

        $this->post(route('login'), [
            'email' => $owner->email,
            'password' => 'CompanyPass!123',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->post(route('login'), [
            'email' => $owner->email,
            'password' => 'LocalOwner!123',
        ])->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticatedAs($owner);
        $this->assertFalse($owner->fresh()->isOrganizationManaged());
    }

    public function test_a_company_account_can_still_sign_in_after_being_handed_ownership(): void
    {
        $this->addOrganizationUser('Imam Rizky', 'imam@example.com', 'MGR', 'ITD');
        $this->post(route('login'), ['email' => 'imam@example.com', 'password' => 'CompanyPass!123'])
            ->assertRedirect();
        $this->post(route('logout'));

        $user = User::where('email', 'imam@example.com')->firstOrFail();
        $membership = $user->workspaceMemberships()->firstOrFail();
        // Promotion used to be a one-way door: the directory is where their password lives, and
        // the owner/admin gate then refused the only credentials they have.
        $membership->update(['role' => WorkspaceRole::OWNER]);
        $membership->workspace->update(['owner_id' => $user->id]);

        $this->post(route('login'), ['email' => 'imam@example.com', 'password' => 'CompanyPass!123'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_registration_is_unavailable_when_company_login_is_enabled(): void
    {
        $this->get(route('register'))->assertNotFound();
        $this->post(route('register'), [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new@example.com',
            'password' => 'SecurePass!123',
            'password_confirmation' => 'SecurePass!123',
        ])->assertNotFound();
    }

    private function addOrganizationUser(string $name, string $email, string $rank, string $department): int
    {
        $connection = DB::connection('organization');
        $divisionId = $connection->table('divisions')->where('code', $department.'-DIV')->value('id')
            ?? $connection->table('divisions')->insertGetId([
                'code' => $department.'-DIV',
                'name' => $department.' Division',
            ]);
        $departmentId = $connection->table('departments')->where('code', $department)->value('id')
            ?? $connection->table('departments')->insertGetId([
                'division_id' => $divisionId,
                'code' => $department,
                'name' => $department.' Department',
            ]);
        $rankId = $connection->table('job_ranks')->where('code', $rank)->value('id')
            ?? $connection->table('job_ranks')->insertGetId([
                'code' => $rank,
                'name' => $rank,
            ]);
        $userId = DB::connection('organization')->table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower($email),
            'password' => Hash::make('CompanyPass!123'),
        ]);
        DB::connection('organization')->table('model_has_job_ranks')->insert([
            'model_id' => $userId,
            'job_rank_id' => $rankId,
        ]);
        DB::connection('organization')->table('model_has_departments')->insert([
            'model_id' => $userId,
            'department_id' => $departmentId,
        ]);

        return $userId;
    }

    private function createOrganizationSchema(): void
    {
        Schema::connection('organization')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
        });
        Schema::connection('organization')->create('job_ranks', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
        });
        Schema::connection('organization')->create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
        });
        Schema::connection('organization')->create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_id');
            $table->string('code');
            $table->string('name');
        });
        Schema::connection('organization')->create('sections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::connection('organization')->create('model_has_job_ranks', function (Blueprint $table): void {
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('job_rank_id');
        });
        Schema::connection('organization')->create('model_has_departments', function (Blueprint $table): void {
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('department_id');
        });
        Schema::connection('organization')->create('model_has_sections', function (Blueprint $table): void {
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('section_id');
        });
    }
}
