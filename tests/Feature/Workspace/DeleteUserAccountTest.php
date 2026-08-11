<?php

namespace Tests\Feature\Workspace;

use App\Actions\Project\CreateSystem;
use App\Actions\User\DeleteUserAccount;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\FeatureRequestStatus;
use App\Enums\RequestUrgency;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeleteUserAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_the_account_disappears_but_the_work_they_raised_does_not(): void
    {
        [$workspace, $owner, $system] = $this->workspace();
        $leaver = $this->member($workspace, WorkspaceRole::REQUESTER);
        $request = $this->request($workspace, $system, $leaver);
        $task = $this->task($system, $leaver);

        app(DeleteUserAccount::class)->handle($leaver, $owner);

        // The person is gone from the users table entirely — no soft delete, no stale email.
        $this->assertDatabaseMissing('users', ['id' => $leaver->id]);

        // The request they raised survives, reassigned rather than deleted.
        $request->refresh();
        $this->assertSame('Export the monthly stock report', $request->title);
        $this->assertNotSame($leaver->id, $request->requester_id);
        $this->assertSame('Deleted user', $request->requester->name);

        // So does work they created but somebody else may be doing.
        $this->assertSame('Deleted user', $task->fresh()->creator->name);
    }

    public function test_their_own_belongings_go_with_them(): void
    {
        [$workspace, $owner, $system] = $this->workspace();
        $leaver = $this->member($workspace, WorkspaceRole::MEMBER);
        $task = $this->task($system, $owner);
        $task->assignees()->attach($leaver->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);

        app(DeleteUserAccount::class)->handle($leaver, $owner);

        $this->assertDatabaseMissing('workspace_members', ['user_id' => $leaver->id]);
        $this->assertDatabaseMissing('task_assignees', ['user_id' => $leaver->id]);
        // The task itself is untouched: an unassigned task is still work that exists.
        $this->assertNotNull($task->fresh());
    }

    public function test_one_placeholder_is_shared_rather_than_one_per_deletion(): void
    {
        [$workspace, $owner, $system] = $this->workspace();
        $first = $this->member($workspace, WorkspaceRole::REQUESTER);
        $second = $this->member($workspace, WorkspaceRole::REQUESTER);
        $this->request($workspace, $system, $first);
        $this->request($workspace, $system, $second, 'Second request');

        app(DeleteUserAccount::class)->handle($first, $owner);
        app(DeleteUserAccount::class)->handle($second, $owner);

        $this->assertSame(1, User::where('email', DeleteUserAccount::PLACEHOLDER_EMAIL)->count());
        $this->assertSame(2, FeatureRequest::count());
    }

    public function test_the_placeholder_never_shows_up_as_a_member_or_a_pic(): void
    {
        [$workspace, $owner, $system] = $this->workspace();
        $leaver = $this->member($workspace, WorkspaceRole::REQUESTER);
        $this->request($workspace, $system, $leaver);

        app(DeleteUserAccount::class)->handle($leaver, $owner);
        $placeholder = User::where('email', DeleteUserAccount::PLACEHOLDER_EMAIL)->firstOrFail();

        // Every picker in the product is driven by membership, so owning none keeps it out of
        // all of them without a single extra filter.
        $this->assertSame(0, $placeholder->workspaceMemberships()->count());
        $this->actingAs($owner)
            ->get(route('app.settings.master.systems', $workspace))
            ->assertOk()
            ->assertDontSee('Deleted user');
    }

    public function test_an_owner_cannot_be_deleted_until_ownership_moves(): void
    {
        [$workspace, $owner] = $this->workspace();
        $admin = $this->member($workspace, WorkspaceRole::ADMIN);

        try {
            app(DeleteUserAccount::class)->handle($owner, $admin);
            $this->fail('Deleting a workspace owner should be refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Transfer ownership', $exception->getMessage());
        }

        // Nothing partial happened: the account and its workspace are both intact.
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_you_cannot_delete_yourself_from_here(): void
    {
        [$workspace, $owner] = $this->workspace();
        $admin = $this->member($workspace, WorkspaceRole::ADMIN);

        $this->expectException(ValidationException::class);
        app(DeleteUserAccount::class)->handle($admin, $admin);
    }

    public function test_polymorphic_rows_that_no_cascade_reaches_are_cleaned_up(): void
    {
        [$workspace, $owner] = $this->workspace();
        $leaver = $this->member($workspace, WorkspaceRole::MEMBER);

        DB::table('push_subscriptions')->insert([
            'subscribable_type' => $leaver->getMorphClass(),
            'subscribable_id' => $leaver->id,
            'endpoint' => 'https://push.example/'.$leaver->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('notifications')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'test', 'notifiable_type' => $leaver->getMorphClass(), 'notifiable_id' => $leaver->id,
            'data' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        app(DeleteUserAccount::class)->handle($leaver, $owner);

        // Neither table has a foreign key, so nothing would have removed these on its own.
        $this->assertSame(0, DB::table('push_subscriptions')->where('subscribable_id', $leaver->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $leaver->id)->count());
    }

    /** @return array<int, array<int, string>> */
    public static function rolesThatMayDelete(): array
    {
        return [['admin'], ['manager']];
    }

    /** @return array<int, array<int, string>> */
    public static function rolesThatMayNot(): array
    {
        return [['supervisor'], ['member'], ['viewer'], ['requester']];
    }

    /**
     * @dataProvider rolesThatMayDelete
     */
    public function test_owner_admin_and_manager_can_delete_an_account(string $role): void
    {
        [$workspace, $owner, $system] = $this->workspace();
        $actor = $this->member($workspace, WorkspaceRole::from($role));
        $target = $this->member($workspace, WorkspaceRole::REQUESTER);
        $membership = $workspace->memberships()->where('user_id', $target->id)->firstOrFail();

        $this->actingAs($actor)
            ->delete(route('internal.user-accounts.destroy', $membership))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    /**
     * @dataProvider rolesThatMayNot
     */
    public function test_everyone_else_is_refused(string $role): void
    {
        [$workspace] = $this->workspace();
        $actor = $this->member($workspace, WorkspaceRole::from($role));
        $target = $this->member($workspace, WorkspaceRole::REQUESTER);
        $membership = $workspace->memberships()->where('user_id', $target->id)->firstOrFail();

        $this->actingAs($actor)
            ->delete(route('internal.user-accounts.destroy', $membership))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_a_manager_cannot_erase_an_admin(): void
    {
        [$workspace] = $this->workspace();
        $manager = $this->member($workspace, WorkspaceRole::MANAGER);
        $admin = $this->member($workspace, WorkspaceRole::ADMIN);
        $membership = $workspace->memberships()->where('user_id', $admin->id)->firstOrFail();

        // Rank decides: you may only erase someone below you, never sideways or upwards.
        $this->actingAs($manager)
            ->delete(route('internal.user-accounts.destroy', $membership))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_an_admin_elsewhere_cannot_erase_someone_who_also_works_where_they_do_not(): void
    {
        [$workspaceA] = $this->workspace();
        [$workspaceB] = $this->workspace();

        $admin = $this->member($workspaceA, WorkspaceRole::ADMIN);
        $target = $this->member($workspaceA, WorkspaceRole::REQUESTER);
        $workspaceB->memberships()->create([
            'user_id' => $target->id, 'role' => WorkspaceRole::MEMBER,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);
        $membership = $workspaceA->memberships()->where('user_id', $target->id)->firstOrFail();

        $this->actingAs($admin)
            ->from(route('app.workspaces.team', $workspaceA))
            ->delete(route('internal.user-accounts.destroy', $membership))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $target->id]);
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

    private function request(Workspace $workspace, Project $system, User $requester, string $title = 'Export the monthly stock report'): FeatureRequest
    {
        return FeatureRequest::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'requester_id' => $requester->id,
            'title' => $title,
            'problem' => 'We copy the numbers by hand every month and it takes two days.',
            'desired_outcome' => 'A download button producing the columns finance already uses.',
            'urgency' => RequestUrgency::NORMAL,
            'status' => FeatureRequestStatus::PENDING_REVIEW,
        ]);
    }

    private function task(Project $project, User $creator): Task
    {
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        return Task::create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'status_id' => $status->id,
            'creator_id' => $creator->id,
            'title' => 'Existing work',
            'priority' => TaskPriority::MEDIUM,
            'position' => 1024,
        ]);
    }
}
