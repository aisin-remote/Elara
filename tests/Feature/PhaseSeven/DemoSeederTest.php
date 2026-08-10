<?php

namespace Tests\Feature\PhaseSeven;

use App\Models\FeatureRequest;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_local_only_complete_and_idempotent(): void
    {
        Storage::fake('local');
        $this->app->instance('env', 'local');

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertDatabaseCount('users', 8);
        $this->assertDatabaseHas('workspaces', ['name' => 'Product Studio']);

        $workspace = Workspace::where('name', 'Product Studio')->firstOrFail();
        $roles = [
            'owner@example.com' => ['owner', 'manager'],
            'manager@example.com' => ['admin', 'manager'],
            'member@example.com' => ['member', 'member'],
            'viewer@example.com' => ['viewer', 'viewer'],
            'supervisor@example.com' => ['supervisor', 'member'],
            'lead@example.com' => ['manager', 'member'],
        ];

        foreach ($roles as $email => [$workspaceRole, $projectRole]) {
            $user = User::where('email', $email)->firstOrFail();

            $this->assertDatabaseHas('workspace_members', [
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => $workspaceRole,
            ]);

            // Only the two hand-built demo projects put everyone on the board. Systems and
            // the delivery project created from a request have their own, narrower membership.
            foreach ($workspace->projects()->whereIn('name', ['Website Redesign', 'Mobile App Development'])->get() as $project) {
                $this->assertDatabaseHas('project_members', [
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                    'role' => $projectRole,
                ]);
            }
        }

        foreach (['requester@example.com', 'department-head@example.com'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $this->assertDatabaseHas('workspace_members', [
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'requester',
            ]);
        }

        // 2 demo projects + 1 delivery project from the approved request; 3 systems beside them.
        $this->assertSame(3, $workspace->projects()->delivery()->count());
        $this->assertSame(3, $workspace->projects()->systems()->count());
        $this->assertDatabaseCount('tasks', 20);
        $this->assertDatabaseCount('schedule_events', 4);
        $this->assertDatabaseCount('files', 1);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 3);
        $this->assertDatabaseCount('notifications', 1);
        Storage::disk('local')->assertExists('demo/orbitra-product-brief.txt');

        // The request layer is seeded once, not once per run.
        $this->assertDatabaseCount('feature_requests', 6);
        $this->assertDatabaseCount('project_requests', 4);
        $this->assertSame(2, FeatureRequest::where('workspace_id', $workspace->id)->where('status', 'scheduled')->count());
    }

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(RuntimeException::class);

        (new DemoSeeder)->run();
    }
}
