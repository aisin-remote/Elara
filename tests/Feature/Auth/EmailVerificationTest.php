<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_can_be_required_by_configuration(): void
    {
        config(['orbitra.email_verification' => true]);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('app.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_email_can_be_verified_with_signed_public_id_url(): void
    {
        config(['orbitra.email_verification' => true]);
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->public_id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect(route('app.dashboard'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_link_for_another_public_id_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $other->public_id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();
    }
}
