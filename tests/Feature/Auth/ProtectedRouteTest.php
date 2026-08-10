<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtectedRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('app.dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_workspace_enters_onboarding(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.dashboard'))
            ->assertRedirect(route('app.workspaces.create'));
    }
}
