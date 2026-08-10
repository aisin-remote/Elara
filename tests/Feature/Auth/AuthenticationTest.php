<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_rendered(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('Sign in')
            // The form itself, not just the heading: the split layout moved everything around it.
            ->assertSee('Keep me signed in')
            // The route, not the label: a formatter that wraps the link text across two lines
            // breaks a string match while the page itself is perfectly fine.
            ->assertSee(route('password.request'));
    }

    public function test_user_can_login_and_session_is_regenerated(): void
    {
        $user = User::factory()->create(['password' => 'SecurePass!123']);
        $this->startSession();
        $oldSessionId = session()->getId();

        $this->post(route('login'), [
            'email' => strtoupper($user->email),
            'password' => 'SecurePass!123',
        ])->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
    }

    public function test_login_failure_is_generic(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_remember_me_creates_a_recaller_token(): void
    {
        $user = User::factory()->create([
            'password' => 'SecurePass!123',
            'remember_token' => null,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'SecurePass!123',
            'remember' => '1',
        ])->assertRedirect(route('app.dashboard'));

        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_login_is_rate_limited_by_email_and_ip(): void
    {
        $user = User::factory()->create();
        $request = LoginRequest::create('/login', 'POST', ['email' => $user->email]);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $key = $request->throttleKey();

        RateLimiter::clear($key);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ]);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_user_can_logout_and_session_data_is_invalidated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['private-state' => 'secret'])
            ->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('private-state');

        $this->assertGuest();
    }
}
