<?php

namespace Tests\Feature\Workspace;

use App\Actions\Workspace\CreateWorkspace;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_workspace_and_becomes_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('internal.workspaces.store'), [
            'name' => 'Orbitra Studio',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'week_start' => 1,
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Orbitra Studio');
        $workspace = Workspace::firstOrFail();
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::OWNER->value,
            'status' => WorkspaceMemberStatus::ACTIVE->value,
        ]);
        $this->assertSame(26, strlen($workspace->public_id));
    }

    public function test_intended_user_can_accept_invitation(): void
    {
        [$owner, $workspace] = $this->workspace();
        $invitee = User::factory()->create(['email' => 'invitee@example.test']);
        $token = Str::random(64);
        WorkspaceInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'invited_by' => $owner->id,
            'email' => $invitee->email,
            'role' => WorkspaceRole::MEMBER,
            'token_hash' => hash('sha256', $token),
        ]);

        $this->actingAs($invitee)
            ->postJson(route('internal.invitations.accept', $token))
            ->assertOk()
            ->assertJsonPath('data.workspace_public_id', $workspace->public_id);

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $invitee->id,
            'role' => WorkspaceRole::MEMBER->value,
            'status' => WorkspaceMemberStatus::ACTIVE->value,
        ]);
    }

    public function test_admin_can_send_hashed_invitation_notification(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->workspace();

        $this->actingAs($owner)->postJson(route('internal.invitations.store', $workspace), [
            'email' => 'new-member@example.test',
            'role' => WorkspaceRole::MEMBER->value,
        ])->assertCreated();

        Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);
        $invitation = WorkspaceInvitation::firstOrFail();
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertNotSame($invitation->token_hash, 'new-member@example.test');
    }

    public function test_expired_invitation_is_rejected(): void
    {
        [$owner, $workspace] = $this->workspace();
        $invitee = User::factory()->create(['email' => 'expired@example.test']);
        $token = Str::random(64);
        WorkspaceInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'invited_by' => $owner->id,
            'email' => $invitee->email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($invitee)
            ->postJson(route('internal.invitations.accept', $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invitation');

        $this->assertDatabaseMissing('workspace_members', ['workspace_id' => $workspace->id, 'user_id' => $invitee->id]);
    }

    public function test_invitation_cannot_be_used_by_another_email(): void
    {
        [$owner, $workspace] = $this->workspace();
        $token = Str::random(64);
        WorkspaceInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'invited_by' => $owner->id,
            'email' => 'intended@example.test',
            'token_hash' => hash('sha256', $token),
        ]);

        $this->actingAs(User::factory()->create(['email' => 'different@example.test']))
            ->postJson(route('internal.invitations.accept', $token))
            ->assertForbidden();
    }

    public function test_owner_can_change_member_role_and_viewer_cannot_mutate_workspace(): void
    {
        [$owner, $workspace] = $this->workspace();
        $viewer = User::factory()->create();
        $membership = $workspace->memberships()->create([
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::VIEWER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)->patchJson(route('internal.workspace-members.update', $membership), [
            'role' => WorkspaceRole::MEMBER->value,
            'status' => WorkspaceMemberStatus::ACTIVE->value,
        ])->assertOk();
        $this->assertSame(WorkspaceRole::MEMBER, $membership->fresh()->role);

        $membership->update(['role' => WorkspaceRole::VIEWER]);
        $this->actingAs($viewer)->patchJson(route('internal.workspaces.update', $workspace), [
            'name' => 'Unauthorized change',
            'timezone' => 'UTC',
            'locale' => 'en',
            'week_start' => 1,
        ])->assertForbidden();
    }

    public function test_workspace_binding_hides_other_workspaces_and_uses_public_id(): void
    {
        [$user, $workspace] = $this->workspace();
        [, $otherWorkspace] = $this->workspace();

        $this->actingAs($user)->get(route('app.workspaces.show', $otherWorkspace))->assertNotFound();
        $this->actingAs($user)->get('/app/workspaces/'.$workspace->id)->assertNotFound();
        $this->actingAs($user)->get(route('app.workspaces.show', $workspace))->assertOk()->assertSee($workspace->name);
    }

    public function test_owner_can_transfer_ownership_without_breaking_single_owner_invariant(): void
    {
        [$owner, $workspace] = $this->workspace();
        $newOwner = User::factory()->create();
        $membership = $workspace->memberships()->create([
            'user_id' => $newOwner->id,
            'role' => WorkspaceRole::MEMBER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)->postJson(route('internal.workspaces.transfer', $workspace), [
            'member_public_id' => $membership->public_id,
        ])->assertOk();

        $this->assertSame($newOwner->id, $workspace->fresh()->owner_id);
        $this->assertSame(WorkspaceRole::OWNER, $membership->fresh()->role);
        $this->assertSame(WorkspaceRole::ADMIN, $workspace->memberships()->where('user_id', $owner->id)->firstOrFail()->role);
        $this->assertSame(1, $workspace->memberships()->where('role', WorkspaceRole::OWNER->value)->count());
    }

    public function test_workspace_team_and_settings_pages_render(): void
    {
        [$owner, $workspace] = $this->workspace();

        $this->actingAs($owner)->get(route('app.workspaces.team', $workspace))->assertOk()->assertSee('Workspace members');
        $this->actingAs($owner)->get(route('app.workspaces.settings', $workspace))->assertOk()->assertSee('Workspace details');
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => fake()->company(),
            'timezone' => 'UTC',
            'locale' => 'en',
            'week_start' => 1,
        ]);

        return [$owner, $workspace];
    }
}
