<?php

namespace App\Http\Requests\Auth;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\OrganizationDirectory;
use App\Services\OrganizationLogin;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->email))]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function authenticate(OrganizationLogin $organizationLogin, OrganizationDirectory $directory): void
    {
        $this->ensureIsNotRateLimited();

        $email = $this->string('email')->toString();
        $password = $this->string('password')->toString();
        $user = User::where('email', $email)->first();
        $protectedLocalAccount = $user?->workspaceMemberships()->active()
            ->whereIn('role', ['owner', 'admin'])
            ->exists() ?? false;
        $organizationManaged = config('organization.jit_auth') && (
            $user?->isOrganizationManaged()
            || ($user && ! $protectedLocalAccount && $directory->profile($user))
        );

        $authenticated = $organizationManaged
            ? (bool) $organizationLogin->attempt($email, $password)
            : Auth::attempt($this->only('email', 'password'), $this->boolean('remember'));

        if (! $authenticated && ! $user) {
            $authenticated = (bool) $organizationLogin->attempt($email, $password);
        }

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());
            SecurityEvent::record($user, 'login.failed', $this->ip(), $this->userAgent(), [
                'email_hash' => hash('sha256', $email),
            ]);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
