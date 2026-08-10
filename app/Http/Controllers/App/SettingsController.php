<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\TwoFactorService;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function securityDefault(): RedirectResponse
    {
        $workspace = request()->user()->workspaceMemberships()->active()->with('workspace')->first()?->workspace;
        abort_unless($workspace, 404);

        return redirect()->route('app.settings.security', $workspace);
    }

    public function profile(Workspace $workspace): View
    {
        $this->authorize('view', $workspace);

        return view('app.settings.profile', [
            'workspace' => $workspace,
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function security(Workspace $workspace, TwoFactorService $twoFactor): View
    {
        $this->authorize('view', $workspace);
        $user = request()->user();
        $sessions = collect();

        if (config('session.driver') === 'database') {
            $sessions = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->get();
        }

        return view('app.settings.security', [
            'workspace' => $workspace,
            'sessions' => $sessions,
            'currentSessionId' => request()->session()->getId(),
            'securityEvents' => $user->securityEvents()->latest('created_at')->limit(12)->get(),
            'qrDataUri' => $twoFactor->qrDataUri($user),
            'recoveryCodes' => session()->pull('two_factor_recovery_codes', []),
        ]);
    }
}
