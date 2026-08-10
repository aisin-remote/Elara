<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Requests\Settings\ConfirmTwoFactorRequest;
use App\Http\Requests\Settings\DisableTwoFactorRequest;
use App\Http\Requests\Settings\SecurityPasswordRequest;
use App\Models\SecurityEvent;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function enable(SecurityPasswordRequest $request, TwoFactorService $twoFactor): JsonResponse|RedirectResponse
    {
        $twoFactor->begin($request->user());
        SecurityEvent::record($request->user(), 'two_factor.enrollment_started', $request->ip(), $request->userAgent());

        return $this->success($request, null, 'Scan the QR code and confirm your authenticator code.', url()->previous());
    }

    public function confirm(ConfirmTwoFactorRequest $request, TwoFactorService $twoFactor): JsonResponse|RedirectResponse
    {
        $codes = $twoFactor->confirm($request->user(), $request->string('code')->toString());
        if ($codes === []) {
            throw ValidationException::withMessages(['code' => 'The authenticator code is invalid.']);
        }

        SecurityEvent::record($request->user(), 'two_factor.enabled', $request->ip(), $request->userAgent());
        $request->session()->flash('two_factor_recovery_codes', $codes);

        return $this->success($request, ['recovery_codes' => $codes], 'Two-factor authentication enabled.', url()->previous());
    }

    public function disable(DisableTwoFactorRequest $request, TwoFactorService $twoFactor): JsonResponse|RedirectResponse
    {
        if (! $twoFactor->verify($request->user(), $request->string('code')->toString(), false)) {
            throw ValidationException::withMessages(['code' => 'The authentication or recovery code is invalid.']);
        }

        $twoFactor->disable($request->user());
        SecurityEvent::record($request->user(), 'two_factor.disabled', $request->ip(), $request->userAgent());

        return $this->success($request, null, 'Two-factor authentication disabled.', url()->previous());
    }

    public function recoveryCodes(SecurityPasswordRequest $request, TwoFactorService $twoFactor): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->two_factor_confirmed_at, 409);
        $codes = $twoFactor->regenerateRecoveryCodes($request->user());
        SecurityEvent::record($request->user(), 'two_factor.recovery_codes_regenerated', $request->ip(), $request->userAgent());
        $request->session()->flash('two_factor_recovery_codes', $codes);

        return $this->success($request, ['recovery_codes' => $codes], 'Recovery codes regenerated.', url()->previous());
    }
}
