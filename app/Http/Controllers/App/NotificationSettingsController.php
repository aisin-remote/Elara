<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationSettingsController extends Controller
{
    public function default(): RedirectResponse
    {
        $workspace = request()->user()->workspaceMemberships()->active()->with('workspace')->first()?->workspace;
        abort_unless($workspace, 404);

        return redirect()->route('app.settings.notifications', $workspace);
    }

    public function edit(Workspace $workspace, NotificationPreferenceService $preferences): View
    {
        $this->authorize('view', $workspace);

        return view('app.settings.notifications', [
            'workspace' => $workspace,
            'preferences' => $preferences->values(request()->user(), $workspace),
            'vapidPublicKey' => config('webpush.vapid.public_key'),
        ]);
    }
}
