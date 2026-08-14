<?php

namespace Tests\Feature\Request;

use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\FeatureRequestStatus;
use App\Models\FeatureRequest;
use App\Models\User;
use Database\Seeders\LegacyFeatureRequestSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyFeatureRequestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_it_approval_returns_to_review_without_reopening_a_real_decision(): void
    {
        config()->set('database.connections.organization', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('organization');
        Schema::connection('organization')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
        });
        DB::connection('organization')->table('users')->insert([
            'id' => 2820,
            'name' => 'Muhammad Ilham Baihaki',
            'email' => 'ilham.baihaki@aiia.co.id',
        ]);

        $owner = User::factory()->create();
        $requester = User::factory()->create([
            'organization_user_id' => 2820,
            'email' => 'ilham.baihaki@aiia.co.id',
        ]);
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => "ITD's Workspace",
            'timezone' => 'Asia/Jakarta',
            'locale' => 'en',
            'week_start' => 1,
        ]);
        app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'QIRA',
            'description' => null,
            'color' => '#6366f1',
            'pic_id' => $owner->id,
        ]);
        config()->set('organization.workspace_public_id', $workspace->public_id);

        $this->seed(LegacyFeatureRequestSeeder::class);

        $request = FeatureRequest::where('requester_id', $requester->id)
            ->where('title', 'Scrap money')
            ->firstOrFail();
        $this->assertSame(FeatureRequestStatus::PENDING_REVIEW, $request->status);

        // Re-running also repairs a row created by the previous seeder implementation.
        $request->forceFill(['status' => FeatureRequestStatus::APPROVED])->save();
        $this->seed(LegacyFeatureRequestSeeder::class);
        $this->assertSame(FeatureRequestStatus::PENDING_REVIEW, $request->fresh()->status);

        // A decision made through Orbitra carries review data and must never be reopened.
        $request->refresh();
        $request->forceFill([
            'status' => FeatureRequestStatus::APPROVED,
            'reviewed_by' => $owner->id,
            'reviewed_at' => now(),
            'estimated_minutes' => 180,
            'version' => 2,
        ])->save();
        $this->seed(LegacyFeatureRequestSeeder::class);
        $this->assertSame(FeatureRequestStatus::APPROVED, $request->fresh()->status);
    }
}
