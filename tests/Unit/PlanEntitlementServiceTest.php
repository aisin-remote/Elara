<?php

namespace Tests\Unit;

use App\Actions\Workspace\CreateWorkspace;
use App\Models\User;
use App\Services\PlanEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanEntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_limits_remain_unlimited_even_if_legacy_plan_config_is_changed(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Studio',
            'timezone' => 'UTC',
            'locale' => 'en',
            'week_start' => 1,
        ]);
        config(['plans.plans.starter.limits.members' => 0]);

        $service = app(PlanEntitlementService::class);

        $this->assertSame('unlimited', $service->plan($workspace));
        $this->assertNull($service->limit($workspace, 'members'));
        $service->assertCanInviteMember($workspace);
    }
}
