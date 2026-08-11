<?php

namespace App\Http\Controllers\Desk;

use App\Actions\Request\TransitionFeatureRequest;
use App\Enums\FeatureRequestStatus;
use App\Enums\RequestUrgency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Request\StoreFeatureRequestRequest;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\DepartmentWorkspaceService;
use App\Services\OrganizationDirectory;
use App\Services\RequestProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FeatureRequestController extends Controller
{
    public function create(
        Request $request,
        Workspace $workspace,
        OrganizationDirectory $organization,
        DepartmentWorkspaceService $departmentWorkspaces,
    ): View {
        $this->authorize('create', [FeatureRequest::class, $workspace]);
        $profile = $organization->profile($request->user());
        $deliveryWorkspace = config('organization.jit_auth')
            ? $departmentWorkspaces->deliveryWorkspace()
            : $workspace;

        $systems = $deliveryWorkspace->projects()
            ->systems()
            ->whereNull('archived_at')
            ->with('members:id,public_id,first_name,last_name,avatar_path')
            ->orderBy('name')
            ->get()
            ->filter(fn (Project $system) => $system->pic() !== null);

        return view('desk.requests.create', [
            'workspace' => $workspace,
            'systems' => $systems,
            'urgencies' => RequestUrgency::cases(),
            'organizationProfile' => $profile,
            'needsDepartmentApproval' => $profile ? $organization->requiresDepartmentApproval($profile) : null,
            // Setting expectations before submission beats explaining afterwards.
            'queueDepth' => $systems->mapWithKeys(fn (Project $system) => [
                $system->public_id => FeatureRequest::where('project_id', $system->id)
                    ->whereIn('status', [
                        FeatureRequestStatus::PENDING_DEPARTMENT->value,
                        FeatureRequestStatus::PENDING_REVIEW->value,
                        FeatureRequestStatus::APPROVED->value,
                    ])
                    ->count(),
            ]),
        ]);
    }

    public function store(
        StoreFeatureRequestRequest $request,
        Workspace $workspace,
        TransitionFeatureRequest $transition,
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

        $system = $deliveryWorkspace->projects()->systems()
            ->where('public_id', $request->string('system_public_id'))
            ->firstOrFail();

        $featureRequest = DB::transaction(function () use ($request, $deliveryWorkspace, $system, $organization, $profile) {
            $created = FeatureRequest::create([
                'workspace_id' => $deliveryWorkspace->id,
                'project_id' => $system->id,
                'requester_id' => $request->user()->id,
                'title' => $request->string('title')->toString(),
                'problem' => $request->string('problem')->toString(),
                'desired_outcome' => $request->string('desired_outcome')->toString(),
                'urgency' => $request->string('urgency')->toString(),
                'status' => FeatureRequestStatus::DRAFT,
                ...$organization->snapshot($profile),
            ]);

            ActivityLog::record($deliveryWorkspace, $created, 'feature_request.created', $request->user());

            return $created;
        });

        // Submitting is a transition like any other, so the queue notification comes from
        // the same place every other status change does.
        $transition->handle(
            $featureRequest,
            $needsDepartmentApproval ? FeatureRequestStatus::PENDING_DEPARTMENT : FeatureRequestStatus::PENDING_REVIEW,
            $request->user()
        );

        return redirect()
            ->route('desk.requests.show', $featureRequest)
            ->with('status', $needsDepartmentApproval
                ? 'Request submitted. Your department manager or coordinator will review it first.'
                : 'Request submitted. A supervisor ITD will review it.');
    }

    public function show(Request $request, FeatureRequest $featureRequest, RequestProgressService $progress): View
    {
        $this->authorize('view', $featureRequest);

        return view('desk.requests.show', [
            'request' => $featureRequest->load(['system', 'requester', 'reviewer', 'departmentReviewer']),
            'monitoring' => $progress->build($featureRequest),
            'timeline' => ActivityLog::where('subject_type', $featureRequest->getMorphClass())
                ->where('subject_id', $featureRequest->id)
                ->with('actor')
                ->latest('created_at')
                ->get(),
        ]);
    }

    /** Answering a "needs info" question puts the request back in the queue. */
    public function resubmit(Request $request, FeatureRequest $featureRequest, TransitionFeatureRequest $transition): RedirectResponse
    {
        $this->authorize('resubmit', $featureRequest);

        $validated = $request->validate([
            'problem' => ['required', 'string', 'min:20', 'max:4000'],
            'desired_outcome' => ['required', 'string', 'min:20', 'max:4000'],
        ]);

        $featureRequest->update($validated);
        $transition->handle(
            $featureRequest,
            $featureRequest->needs_info_stage === 'department'
                ? FeatureRequestStatus::PENDING_DEPARTMENT
                : FeatureRequestStatus::PENDING_REVIEW,
            $request->user()
        );

        return back()->with('status', 'Sent back for review.');
    }

    public function withdraw(Request $request, FeatureRequest $featureRequest, TransitionFeatureRequest $transition): RedirectResponse
    {
        $this->authorize('withdraw', $featureRequest);

        $transition->handle(
            $featureRequest,
            FeatureRequestStatus::REJECTED,
            $request->user(),
            'Withdrawn by the requester.'
        );

        return redirect()->route('desk.index')->with('status', 'Request withdrawn.');
    }
}
