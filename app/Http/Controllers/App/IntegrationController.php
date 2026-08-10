<?php

namespace App\Http\Controllers\App;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    public function index(Workspace $workspace): View
    {
        $this->authorize('viewAny', [IntegrationConnection::class, $workspace]);

        return view('app.settings.integrations', [
            'workspace' => $workspace,
            'providers' => IntegrationProvider::cases(),
            'connections' => $workspace->integrationConnections()->with('links')->get()->keyBy(fn ($connection) => $connection->provider->value),
            'projects' => $workspace->projects()->whereNull('archived_at')->orderBy('name')->get(),
            'tasks' => $workspace->tasks()->with('project')->whereNull('archived_at')->latest()->limit(50)->get(),
            'scheduleEvents' => $workspace->scheduleEvents()->where('end_at', '>=', now())->orderBy('start_at')->limit(50)->get(),
        ]);
    }

    public function default(): RedirectResponse
    {
        $user = request()->user();
        $workspace = $user->workspaceMemberships()->active()->with('workspace')->get()
            ->pluck('workspace')
            ->first(fn (Workspace $workspace) => $user->can('manageSettings', $workspace));
        abort_unless($workspace, 404);

        return redirect()->route('app.settings.integrations', $workspace);
    }
}
