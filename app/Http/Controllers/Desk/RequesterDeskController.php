<?php

namespace App\Http\Controllers\Desk;

use App\Http\Controllers\Controller;
use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Models\SupportingTask;
use App\Models\Workspace;
use App\Services\OrganizationDirectory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RequesterDeskController extends Controller
{
    public function index(Request $request, OrganizationDirectory $organization): View
    {
        $workspace = $this->currentWorkspace($request);
        $organizationProfile = $organization->profile($request->user());

        $features = $workspace
            ? FeatureRequest::query()
                ->visibleTo($request->user(), $workspace, $organizationProfile['department_id'] ?? null)
                ->with(['system', 'requester', 'reviewer', 'departmentReviewer'])
                ->latest('created_at')
                ->get()
            : collect();

        $projects = $workspace
            ? ProjectRequest::query()
                ->visibleTo($request->user(), $workspace)
                ->with(['supervisor', 'manager'])
                ->latest('created_at')
                ->get()
            : collect();

        // Split on the enum rather than a list of status strings here: the enum already owns
        // what "finished" means, and a second copy of that list would drift from it.
        $openFeatures = $features->filter(fn ($row) => $row->status->isOpen());
        $openProjects = $projects->filter(fn ($row) => $row->status->isOpen());
        $history = $features->reject(fn ($row) => $row->status->isOpen())
            ->concat($projects->reject(fn ($row) => $row->status->isOpen()))
            ->sortByDesc('created_at')
            ->values();

        $supporting = $organizationProfile
            ? SupportingTask::query()
                ->where('creator_id', $request->user()->id)
                ->with(['creator', 'assignee'])
                ->latest('created_at')->get()
            : collect();

        $counts = [
            'feature' => $openFeatures->count(),
            'project' => $openProjects->count(),
            'supporting' => $supporting->count(),
            'history' => $history->count(),
        ];

        $tab = $this->activeTab($request->string('tab')->toString(), $counts);
        $status = $request->string('status')->toString();

        $rows = match ($tab) {
            'project' => $openProjects,
            'supporting' => $supporting,
            'history' => $history,
            default => $openFeatures,
        };

        return view('desk.index', [
            'workspace' => $workspace,
            'organizationProfile' => $organizationProfile,
            'tab' => $tab,
            'counts' => $counts,
            // Only the statuses actually present: a filter offering choices that return
            // nothing teaches people the filter is broken.
            'statuses' => $rows->map(fn ($row) => $row->status)->unique()->values(),
            'status' => $status,
            'rows' => $status === '' ? $rows->values() : $rows->filter(fn ($row) => $row->status->value === $status)->values(),
        ]);
    }

    /** @param  array<string, int>  $counts */
    private function activeTab(string $requested, array $counts): string
    {
        if (array_key_exists($requested, $counts)) {
            return $requested;
        }

        foreach ($counts as $tab => $count) {
            if ($count > 0) {
                return $tab;
            }
        }

        return 'feature';
    }

    private function currentWorkspace(Request $request): ?Workspace
    {
        return $request->user()->workspaceMemberships()
            ->active()
            ->with('workspace')
            ->first()?->workspace;
    }
}
