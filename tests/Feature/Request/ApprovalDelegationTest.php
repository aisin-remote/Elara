<?php

namespace Tests\Feature\Request;

use App\Actions\Workspace\CreateWorkspace;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\FeatureRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalDelegationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approver_can_delegate_a_scoped_queue_to_an_active_member(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'ITD', 'timezone' => 'Asia/Jakarta', 'locale' => 'en', 'week_start' => 1,
        ]);
        $backup = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $backup->id, 'role' => WorkspaceRole::MEMBER,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);

        $this->actingAs($owner)->post(route('internal.approval-delegations.store', $workspace), [
            'delegate_public_id' => $backup->public_id,
            'scope' => 'feature',
            'starts_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertTrue($backup->can('viewAny', [FeatureRequest::class, $workspace]));
        $this->assertDatabaseHas('approval_delegations', [
            'workspace_id' => $workspace->id, 'delegator_id' => $owner->id,
            'delegate_id' => $backup->id, 'scope' => 'feature',
        ]);
    }
}
