<?php

namespace App\Http\Controllers\Desk;

use App\Actions\Request\TransitionFeatureRequest;
use App\Actions\Request\TransitionProjectRequest;
use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Request\DecideDepartmentRequest;
use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Models\Workspace;
use App\Services\OrganizationDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentApprovalController extends Controller
{
    public function index(Request $request, Workspace $workspace, OrganizationDirectory $organization): View
    {
        $this->authorize('viewDepartmentApprovals', [FeatureRequest::class, $workspace]);
        $this->authorize('viewDepartmentApprovals', [ProjectRequest::class, $workspace]);
        $profile = $organization->requireProfile($request->user());

        return view('desk.approvals.index', [
            'workspace' => $workspace,
            'profile' => $profile,
            'features' => FeatureRequest::query()
                ->where('requester_department_external_id', $profile['department_id'])
                ->awaitingDepartment()
                ->with(['requester', 'system'])
                ->oldest('created_at')
                ->get(),
            'projects' => ProjectRequest::query()
                ->where('requester_department_external_id', $profile['department_id'])
                ->awaitingDepartment()
                ->with('requester')
                ->oldest('created_at')
                ->get(),
        ]);
    }

    public function decideFeature(
        DecideDepartmentRequest $request,
        Workspace $workspace,
        FeatureRequest $featureRequest,
        TransitionFeatureRequest $transition,
    ): RedirectResponse {
        abort_unless(
            $workspace->organization_department_id !== null
                ? $featureRequest->requester_department_external_id === $workspace->organization_department_id
                : $featureRequest->workspace_id === $workspace->id,
            404,
        );

        $next = match ($request->string('decision')->toString()) {
            'approve' => FeatureRequestStatus::PENDING_REVIEW,
            'reject' => FeatureRequestStatus::REJECTED,
            default => FeatureRequestStatus::NEEDS_INFO,
        };

        $transition->handle($featureRequest, $next, $request->user(), $request->input('note'));

        return redirect()->route('desk.department-approvals.index', $workspace)
            ->with('status', $this->message($next->value));
    }

    public function decideProject(
        DecideDepartmentRequest $request,
        Workspace $workspace,
        ProjectRequest $projectRequest,
        TransitionProjectRequest $transition,
    ): RedirectResponse {
        abort_unless(
            $workspace->organization_department_id !== null
                ? $projectRequest->requester_department_external_id === $workspace->organization_department_id
                : $projectRequest->workspace_id === $workspace->id,
            404,
        );

        $next = match ($request->string('decision')->toString()) {
            'approve' => ProjectRequestStatus::PENDING_MEETING,
            'reject' => ProjectRequestStatus::REJECTED,
            default => ProjectRequestStatus::NEEDS_INFO,
        };

        $transition->handle($projectRequest, $next, $request->user(), $request->input('note'));

        return redirect()->route('desk.department-approvals.index', $workspace)
            ->with('status', $this->message($next->value));
    }

    private function message(string $status): string
    {
        return match ($status) {
            FeatureRequestStatus::PENDING_REVIEW->value => 'Disetujui. Permintaan fitur diteruskan ke supervisor ITD.',
            ProjectRequestStatus::PENDING_MEETING->value => 'Disetujui. Usulan proyek diteruskan ke ITD untuk rapat scoping.',
            'rejected' => 'Permintaan ditolak dan requester sudah diberi tahu.',
            default => 'Permintaan dikembalikan untuk dilengkapi.',
        };
    }
}
