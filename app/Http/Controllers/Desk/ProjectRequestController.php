<?php

namespace App\Http\Controllers\Desk;

use App\Actions\Request\TransitionProjectRequest;
use App\Enums\ProjectRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Request\StoreProjectRequestRequest;
use App\Models\ActivityLog;
use App\Models\ProjectRequest;
use App\Models\Workspace;
use App\Services\DepartmentWorkspaceService;
use App\Services\OrganizationDirectory;
use App\Services\RequestProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectRequestController extends Controller
{
    public function create(Request $request, Workspace $workspace, OrganizationDirectory $organization): View
    {
        $this->authorize('create', [ProjectRequest::class, $workspace]);
        $profile = $organization->profile($request->user());

        return view('desk.project-requests.create', [
            'workspace' => $workspace,
            'organizationProfile' => $profile,
            'needsDepartmentApproval' => $profile ? $organization->requiresDepartmentApproval($profile) : null,
        ]);
    }

    public function store(
        StoreProjectRequestRequest $request,
        Workspace $workspace,
        TransitionProjectRequest $transition,
        OrganizationDirectory $organization,
        DepartmentWorkspaceService $departmentWorkspaces,
    ): RedirectResponse {
        $profile = $organization->requireProfile($request->user());
        $needsDepartmentApproval = $organization->requiresDepartmentApproval($profile);
        $deliveryWorkspace = config('organization.jit_auth')
            ? $departmentWorkspaces->deliveryWorkspace()
            : $workspace;

        if ($needsDepartmentApproval && $organization->departmentApprovers($workspace, $profile['department_id'])->isEmpty()) {
            throw ValidationException::withMessages([
                'organization' => 'Manager atau coordinator department Anda belum memiliki akses aktif ke workspace Orbitra ini.',
            ]);
        }

        $projectRequest = DB::transaction(function () use ($request, $deliveryWorkspace, $organization, $profile) {
            $created = ProjectRequest::create([
                'workspace_id' => $deliveryWorkspace->id,
                'requester_id' => $request->user()->id,
                'status' => ProjectRequestStatus::DRAFT,
                ...$organization->snapshot($profile),
                ...$request->safe()->only(['title', 'benefit', 'concept', 'business_process', 'flow', 'target_date']),
            ]);

            ActivityLog::record($deliveryWorkspace, $created, 'project_request.created', $request->user());

            return $created;
        });

        $transition->handle(
            $projectRequest,
            $needsDepartmentApproval ? ProjectRequestStatus::PENDING_DEPARTMENT : ProjectRequestStatus::PENDING_MEETING,
            $request->user()
        );

        return redirect()
            ->route('desk.project-requests.show', $projectRequest)
            ->with('status', $needsDepartmentApproval
                ? 'Request submitted. Manager atau coordinator department Anda akan meninjaunya lebih dulu.'
                : 'Request submitted. ITD will arrange a scoping meeting with you.');
    }

    public function show(ProjectRequest $projectRequest, RequestProgressService $progress): View
    {
        $this->authorize('view', $projectRequest);

        return view('desk.project-requests.show', [
            'request' => $projectRequest->load(['requester', 'departmentReviewer', 'supervisor', 'manager', 'meeting.attendees']),
            'monitoring' => $progress->build($projectRequest),
            'timeline' => ActivityLog::where('subject_type', $projectRequest->getMorphClass())
                ->where('subject_id', $projectRequest->id)
                ->with('actor')
                ->latest('created_at')
                ->get(),
        ]);
    }

    public function resubmit(Request $request, ProjectRequest $projectRequest, TransitionProjectRequest $transition): RedirectResponse
    {
        $this->authorize('resubmit', $projectRequest);

        $projectRequest->update($request->validate([
            'benefit' => ['required', 'string', 'min:40', 'max:4000'],
            'concept' => ['required', 'string', 'min:40', 'max:4000'],
            'business_process' => ['required', 'string', 'min:40', 'max:4000'],
            'flow' => ['required', 'string', 'min:40', 'max:4000'],
        ]));

        $transition->handle(
            $projectRequest,
            $projectRequest->needs_info_stage === 'department'
                ? ProjectRequestStatus::PENDING_DEPARTMENT
                : ProjectRequestStatus::PENDING_SPV,
            $request->user()
        );

        return back()->with('status', 'Sent back for review.');
    }

    public function withdraw(Request $request, ProjectRequest $projectRequest, TransitionProjectRequest $transition): RedirectResponse
    {
        $this->authorize('withdraw', $projectRequest);

        $transition->handle($projectRequest, ProjectRequestStatus::REJECTED, $request->user(), 'Withdrawn by the requester.');

        return redirect()->route('desk.index')->with('status', 'Request withdrawn.');
    }
}
