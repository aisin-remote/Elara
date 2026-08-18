<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\SecurityEvent;
use App\Services\DepartmentPreference;
use App\Services\OrganizationDirectory;
use App\Services\OrganizationLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(
        LoginRequest $request,
        OrganizationDirectory $organization,
        OrganizationLogin $organizationLogin,
        DepartmentPreference $departments,
    ): RedirectResponse {
        $request->authenticate($organizationLogin, $organization);
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            $request->session()->put([
                'login.two_factor_user_id' => $user->id,
                'login.remember' => ! $user->isOrganizationManaged() && $request->boolean('remember'),
            ]);
            Auth::guard('web')->logout();
            $request->session()->regenerate();

            return redirect()->route('two-factor.login');
        }

        $request->session()->regenerate();
        $organization->syncMembershipRoles($user);
        $request->session()->put('organization_role_synced', true);
        SecurityEvent::record($user, 'login.succeeded', $request->ip(), $request->userAgent(), ['two_factor' => false]);

        // Requesters skip intended(): a stored /app URL would land them on a 403.
        $response = $user->isRequester()
            ? redirect()->to($user->homePath())
            : redirect()->intended(route('app.dashboard', absolute: false));

        if (($profile = $organization->profile($user)) && ($cookie = $departments->remember($profile))) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
