<?php

namespace Tests\Feature\Console;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneOrphanedDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_rows_left_by_a_user_deleted_outside_orbitra(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create();
        $workspace->memberships()->create([
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        DB::table('task_breakdowns')->insert([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'subject_type' => $workspace->getMorphClass(),
            'subject_id' => $workspace->id,
            'provider' => 'test',
            'model' => 'test',
            'status' => 'ready',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::disableForeignKeyConstraints();
        DB::table('users')->where('id', $owner->id)->delete();
        Schema::enableForeignKeyConstraints();

        $this->artisan('orbitra:prune-orphaned-data', ['--force' => true])
            ->expectsOutput('Orphaned data removed.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
        $this->assertDatabaseMissing('workspace_members', ['workspace_id' => $workspace->id]);
        $this->assertDatabaseMissing('task_breakdowns', [
            'subject_type' => $workspace->getMorphClass(),
            'subject_id' => $workspace->id,
        ]);
    }
}
