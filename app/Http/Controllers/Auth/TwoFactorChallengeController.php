<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\DepartmentPreference;
use App\Services\OrganizationDirectory;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (! session()->has('login.two_factor_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(
        TwoFactorChallengeRequest $request,
        TwoFactorService $twoFactor,
        OrganizationDirectory $organization,
        DepartmentPreference $departments,
    ): RedirectResponse {
        $key = 'two-factor:'.$request->session()->getId().'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $user = User::findOrFail($request->session()->get('login.two_factor_user_id'));
        if (! $twoFactor->verify($user, $request->string('code')->toString())) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['code' => 'The authentication or recovery code is invalid.']);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget('login.two_factor_user_id');
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $organization->syncMembershipRoles($user);
        $request->session()->put('organization_role_synced', true);
        SecurityEvent::record($user, 'login.succeeded', $request->ip(), $request->userAgent(), ['two_factor' => true]);

        $response = $user->isRequester()
            ? redirect()->to($user->homePath())
            : redirect()->intended(route('app.dashboard', absolute: false));

        if (($profile = $organization->profile($user)) && ($cookie = $departments->remember($profile))) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
