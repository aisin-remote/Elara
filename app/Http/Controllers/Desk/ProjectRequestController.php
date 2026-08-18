<?php

namespace App\Http\Controllers\Desk;

use App\Actions\Request\TransitionProjectRequest;
use App\Enums\ProjectRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Request\ResubmitProjectRequestRequest;
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
                'organization' => 'Your department manager or coordinator does not have active Orbitra access yet.',
            ]);
        }

        $projectRequest = DB::transaction(function () use ($request, $deliveryWorkspace, $organization, $profile) {
            $created = ProjectRequest::create([
                'workspace_id' => $deliveryWorkspace->id,
                'requester_id' => $request->user()->id,
                'status' => ProjectRequestStatus::DRAFT,
                ...$organization->snapshot($profile),
                ...$this->payload($request->validated()),
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
                ? 'Request submitted. Your department manager or coordinator will review it first.'
                : 'Request submitted. ITD will arrange a scoping meeting with you.');
    }

    public function show(Request $request, ProjectRequest $projectRequest, RequestProgressService $progress): View
    {
        $this->authorize('view', $projectRequest);

        return view('desk.project-requests.show', [
            'request' => $projectRequest->load(['requester', 'departmentReviewer', 'supervisor', 'manager', 'meeting.attendees']),
            'monitoring' => $progress->build($projectRequest, $request->user()),
            'timeline' => ActivityLog::where('subject_type', $projectRequest->getMorphClass())
                ->where('subject_id', $projectRequest->id)
                ->with('actor')
                ->latest('created_at')
                ->get(),
        ]);
    }

    public function resubmit(ResubmitProjectRequestRequest $request, ProjectRequest $projectRequest, TransitionProjectRequest $transition): RedirectResponse
    {
        $projectRequest->update($this->payload($request->validated()));

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

    private function payload(array $validated): array
    {
        $objectives = collect($validated['objectives'])
            ->map(fn (array $objective) => $objective['title'].': '.$objective['description'])
            ->implode("\n");
        $benefits = collect($validated['benefits'])->implode("\n");
        $costs = collect($validated['cost_items'])->implode("\n");

        return [
            ...$validated,
            // Keep the existing AI breakdown and project creation pipeline compatible.
            'business_process' => $validated['background'],
            'concept' => $objectives."\n\nExpected future state:\n".$validated['after_state'],
            'flow' => $validated['illustration']."\n\nBefore:\n".$validated['before_state']."\n\nAfter:\n".$validated['after_state'],
            'benefit' => $benefits."\n\nExpected costs:\n".$costs."\n\nCost & ROI:\n".$validated['roi'],
        ];
    }
}
