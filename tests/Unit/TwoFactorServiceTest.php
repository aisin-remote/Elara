<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_totp_and_single_use_recovery_code_boundary(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorService::class);
        $service->begin($user);
        $user->refresh();
        $codes = $service->confirm($user, (new Google2FA)->getCurrentOtp($user->two_factor_secret));

        $this->assertCount(8, $codes);
        $this->assertTrue($service->verify($user->fresh(), $codes[0]));
        $this->assertFalse($service->verify($user->fresh(), $codes[0]));
        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);
    }
}
