<?php

namespace Tests\Feature\Billing;

use App\Actions\Workspace\CreateWorkspace;
use App\Models\User;
use App\Services\PlanEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BillingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_workspace_entitlement_is_unlimited(): void
    {
        [, $workspace] = $this->workspace();
        $entitlements = app(PlanEntitlementService::class);

        $this->assertSame('unlimited', $entitlements->plan($workspace));
        $this->assertSame('Unlimited', $entitlements->details($workspace)['name']);
        $this->assertNull($entitlements->limit($workspace, 'members'));
        $this->assertNull($entitlements->limit($workspace, 'active_projects'));
        $this->assertNull($entitlements->limit($workspace, 'storage_bytes'));
        $this->assertNull($entitlements->limit($workspace, 'integrations'));
        $this->assertTrue($entitlements->limit($workspace, 'exports'));

        $entitlements->assertCanInviteMember($workspace);
        $entitlements->assertCanCreateProject($workspace);
        $entitlements->assertCanStoreBytes($workspace, PHP_INT_MAX);
        $entitlements->assertCanConnectIntegration($workspace);
        $entitlements->assertCanExport($workspace);
    }

    public function test_member_invitations_are_not_blocked_by_the_old_plan_configuration(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->workspace();
        config(['plans.plans.starter.limits.members' => 0]);

        $this->actingAs($owner)->postJson(route('internal.invitations.store', $workspace), [
            'email' => 'next@example.test',
            'role' => 'member',
        ])->assertCreated();

        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email' => 'next@example.test',
        ]);
    }

    public function test_billing_routes_and_settings_entry_are_removed(): void
    {
        [$owner, $workspace] = $this->workspace();

        $this->assertFalse(Route::has('app.settings.subscription'));
        $this->assertFalse(Route::has('settings.subscription'));
        $this->assertFalse(Route::has('internal.billing.checkout'));
        $this->assertFalse(Route::has('stripe.webhook'));

        $this->actingAs($owner)->get(route('app.workspaces.settings', $workspace))
            ->assertOk()
            ->assertDontSee('Subscription');
    }

    public function test_marketing_page_describes_unlimited_access_without_packages(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Unlimited access')
            ->assertSee('No package limits')
            ->assertDontSee('Starter')
            ->assertDontSee('Start free trial');
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Orbitra Studio',
            'timezone' => 'UTC',
            'locale' => 'en',
            'week_start' => 1,
        ]);

        return [$owner, $workspace];
    }
}
