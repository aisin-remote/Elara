<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_rendered(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Create your Orbitra account');
    }

    public function test_user_can_register_with_a_regenerated_session(): void
    {
        $this->startSession();
        $oldSessionId = session()->getId();

        $response = $this->post(route('register'), [
            'first_name' => '  Ada ',
            'last_name' => ' Lovelace  ',
            'email' => ' ADA@EXAMPLE.COM ',
            'password' => 'SecurePass!123',
            'password_confirmation' => 'SecurePass!123',
        ]);

        $response->assertRedirect(route('app.dashboard'));
        $this->assertAuthenticated();
        $this->assertNotSame($oldSessionId, session()->getId());

        $user = User::sole();
        $this->assertSame('Ada', $user->first_name);
        $this->assertSame('Lovelace', $user->last_name);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertTrue(Hash::check('SecurePass!123', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $user->public_id);
    }

    public function test_optional_email_verification_sends_a_public_id_link(): void
    {
        config(['orbitra.email_verification' => true]);
        Notification::fake();

        $this->post(route('register'), [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace@example.com',
            'password' => 'SecurePass!123',
            'password_confirmation' => 'SecurePass!123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::sole();
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return str_contains($url, $user->public_id)
                && ! str_contains($url, "/verify-email/{$user->id}/");
        });
    }
}
