<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\SystemHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class SystemHealthController extends Controller
{
    private const ACTIONS = [
        'retry-job',
        'retry-breakdown',
        'sync-users',
        'rebuild-memberships',
        'drain-requests',
        'integrity-check',
    ];

    public function show(Request $request, Workspace $workspace, SystemHealthService $health): View
    {
        $this->ensureAccess();

        return view('app.settings.system-health', [
            'workspace' => $workspace,
            'health' => $health->snapshot(),
        ]);
    }

    public function run(Request $request, Workspace $workspace, string $action, SystemHealthService $health): RedirectResponse
    {
        $this->ensureAccess();
        validator(['action' => $action], ['action' => ['required', Rule::in(self::ACTIONS)]])->validate();
        $target = $request->validate(['target' => ['nullable', 'string', 'max:64']])['target'] ?? null;

        try {
            $message = match ($action) {
                'retry-job' => $health->retryFailedJob($this->requireTarget($target)),
                'retry-breakdown' => $health->retryBreakdown($this->requireTarget($target)),
                'sync-users' => $health->syncOrganizationUsers(),
                'rebuild-memberships' => $health->rebuildMemberships(),
                'drain-requests' => $health->drainApprovedRequests(),
                'integrity-check' => $health->runIntegrityCheck(),
            };
        } catch (Throwable $exception) {
            report($exception);

            return to_route('app.settings.system-health', $workspace)
                ->withErrors(['system_health' => $exception->getMessage()]);
        }

        return to_route('app.settings.system-health', $workspace)->with('status', $message);
    }

    private function ensureAccess(): void
    {
        abort_unless(Gate::allows('viewSystemHealth'), 404);
    }

    private function requireTarget(?string $target): string
    {
        if (blank($target)) {
            throw new RuntimeException('This action needs a target.');
        }

        return $target;
    }
}
