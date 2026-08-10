<?php

namespace App\Services;

use App\Models\User;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function begin(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => $this->google2fa->generateSecretKey(32),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function confirm(User $user, string $code): array
    {
        if (! $user->two_factor_secret || ! $this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            return [];
        }

        $codes = $this->newRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $value) => hash('sha256', $value), $codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $codes;
    }

    public function verify(User $user, string $code, bool $consumeRecoveryCode = true): bool
    {
        $code = Str::lower(trim($code));

        if (preg_match('/^\d{6}$/', $code) && $user->two_factor_secret && $this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            return true;
        }

        $hash = hash('sha256', $code);
        $codes = $user->two_factor_recovery_codes ?? [];
        $index = array_search($hash, $codes, true);

        if ($index === false) {
            return false;
        }

        if ($consumeRecoveryCode) {
            unset($codes[$index]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
        }

        return true;
    }

    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => array_map(fn (string $value) => hash('sha256', $value), $codes)])->save();

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function qrDataUri(User $user): ?string
    {
        if (! $user->two_factor_secret) {
            return null;
        }

        $uri = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $user->two_factor_secret);
        $result = (new SvgWriter)->write(QrCode::create($uri)->setSize(220)->setMargin(10));

        return $result->getDataUri();
    }

    private function newRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }
}
