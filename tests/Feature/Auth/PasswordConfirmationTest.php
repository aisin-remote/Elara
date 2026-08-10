<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_confirmation_screen_is_rendered(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('password.confirm'))
            ->assertOk();
    }

    public function test_password_can_be_confirmed(): void
    {
        $user = User::factory()->create(['password' => 'SecurePass!123']);

        $this->actingAs($user)->post(route('password.confirm'), [
            'password' => 'SecurePass!123',
        ])->assertRedirect(route('app.dashboard'))
            ->assertSessionHas('auth.password_confirmed_at');
    }

    public function test_incorrect_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'SecurePass!123']);

        $this->actingAs($user)->post(route('password.confirm'), [
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('password');
    }
}
