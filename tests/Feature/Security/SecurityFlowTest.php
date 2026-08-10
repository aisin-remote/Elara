<?php

namespace Tests\Feature\Security;

use App\Actions\Workspace\CreateWorkspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecurityFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_requires_password_for_email_change_and_keeps_avatar_private(): void
    {
        Storage::fake('local');
        [$user, $workspace] = $this->workspace();
        $payload = [
            'first_name' => 'Fabian', 'last_name' => 'Orbitra', 'email' => 'changed@example.test',
            'phone' => '+62 812', 'job_title' => 'Product Lead', 'company' => 'Orbitra', 'bio' => 'Building useful tools.',
            'locale' => 'id', 'timezone' => 'Asia/Jakarta', 'theme' => 'dark',
            'avatar' => UploadedFile::fake()->image('avatar.png', 100, 100),
        ];

        $this->actingAs($user)->patchJson(route('internal.settings.profile.update'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->actingAs($user)->patch(route('internal.settings.profile.update'), [...$payload, 'current_password' => 'password'])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('changed@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('dark', $user->theme);
        Storage::disk('local')->assertExists($user->avatar_path);
        $this->actingAs($user)->get(route('internal.users.avatar', $user))->assertOk();
    }

    public function test_password_change_requires_current_password_and_records_security_activity(): void
    {
        [$user, $workspace] = $this->workspace();
        $payload = ['current_password' => 'wrong', 'password' => 'NewSecure#1234', 'password_confirmation' => 'NewSecure#1234'];

        $this->actingAs($user)->putJson(route('internal.settings.password.update'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->actingAs($user)->putJson(route('internal.settings.password.update'), [...$payload, 'current_password' => 'password'])->assertOk();

        $this->assertTrue(Hash::check('NewSecure#1234', $user->fresh()->password));
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event' => 'password.changed']);
        $this->actingAs($user)->get(route('app.settings.security', $workspace))->assertOk()->assertSee('Security activity');
    }

    public function test_two_factor_enrollment_recovery_codes_and_login_challenge_work(): void
    {
        [$user] = $this->workspace();
        $this->actingAs($user)->postJson(route('internal.security.two-factor.enable'), ['current_password' => 'password'])->assertOk();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotSame($user->two_factor_secret, \DB::table('users')->where('id', $user->id)->value('two_factor_secret'));

        $code = (new Google2FA)->getCurrentOtp($user->two_factor_secret);
        $confirm = $this->actingAs($user)->postJson(route('internal.security.two-factor.confirm'), ['code' => $code])->assertOk();
        $confirm->assertJsonCount(8, 'data.recovery_codes');
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.two_factor_user_id', $user->id);
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($user->fresh()->two_factor_secret)])
            ->assertRedirect(route('app.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event' => 'login.succeeded']);
    }

    public function test_other_database_sessions_can_be_revoked_but_current_session_cannot(): void
    {
        config(['session.driver' => 'database']);
        [$user] = $this->workspace();
        \DB::table('sessions')->insert([
            ['id' => 'other-session', 'user_id' => $user->id, 'ip_address' => '127.0.0.2', 'user_agent' => 'Other browser', 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        $this->actingAs($user)->deleteJson(route('internal.security.sessions.destroy', 'other-session'), ['current_password' => 'password'])->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
    }

    private function workspace(): array
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, ['name' => 'Orbitra Studio', 'timezone' => 'Asia/Jakarta', 'locale' => 'en', 'week_start' => 1]);

        return [$user, $workspace];
    }
}
