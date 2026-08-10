<?php

namespace App\Policies;

use App\Enums\ProjectRequestStatus;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OrganizationDirectory;

class ProjectRequestPolicy
{
    public function __construct(private readonly OrganizationDirectory $organization) {}

    /** Requesters only — same reasoning as FeatureRequestPolicy::create. */
    public function create(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace)?->canUseRequestDesk() === true;
    }

    public function viewAny(User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), [
            WorkspaceRole::SUPERVISOR,
            WorkspaceRole::MANAGER,
            WorkspaceRole::ADMIN,
            WorkspaceRole::OWNER,
        ], true);
    }

    public function view(User $user, ProjectRequest $request): bool
    {
        $role = $this->role($user, $request->workspace);

        return $request->requester_id === $user->id
            || $role?->canAccessDeliveryDesk()
            || ($request->department_reviewed_by === $user->id)
            || ($request->status === ProjectRequestStatus::PENDING_DEPARTMENT
                && $request->requester_department_external_id !== null
                && $this->organization->canApproveDepartment($user, $request->requester_department_external_id));
    }

    /** Scheduling and closing the scoping meeting is the supervisor's job. */
    public function runMeeting(User $user, ProjectRequest $request): bool
    {
        return in_array($this->role($user, $request->workspace), [
            WorkspaceRole::SUPERVISOR,
            WorkspaceRole::ADMIN,
            WorkspaceRole::OWNER,
        ], true);
    }

    /** First signature. Unavailable until the meeting is recorded as held. */
    public function signAsSupervisor(User $user, ProjectRequest $request): bool
    {
        return $request->status === ProjectRequestStatus::PENDING_SPV
            && $request->meetingHeld()
            && $this->runMeeting($user, $request);
    }

    /**
     * Second signature. A user holding both roles still cannot supply both: the Action
     * refuses it too, this is the version the UI can read.
     */
    public function signAsManager(User $user, ProjectRequest $request): bool
    {
        return $request->status === ProjectRequestStatus::PENDING_MANAGER
            && $request->spv_id !== $user->id
            && in_array($this->role($user, $request->workspace), [
                WorkspaceRole::MANAGER,
                WorkspaceRole::ADMIN,
                WorkspaceRole::OWNER,
            ], true);
    }

    public function viewDepartmentApprovals(User $user, Workspace $workspace): bool
    {
        $profile = $this->organization->profile($user);

        return $this->role($user, $workspace)?->canUseRequestDesk() === true
            && $profile !== null
            && $this->organization->canApproveDepartment($user, $profile['department_id']);
    }

    public function departmentDecide(User $user, ProjectRequest $request): bool
    {
        return $request->status === ProjectRequestStatus::PENDING_DEPARTMENT
            && $request->requester_department_external_id !== null
            && $this->organization->canApproveDepartment($user, $request->requester_department_external_id);
    }

    public function withdraw(User $user, ProjectRequest $request): bool
    {
        return $request->requester_id === $user->id
            && in_array($request->status, [
                ProjectRequestStatus::DRAFT,
                ProjectRequestStatus::PENDING_DEPARTMENT,
                ProjectRequestStatus::PENDING_MEETING,
                ProjectRequestStatus::PENDING_SPV,
                ProjectRequestStatus::NEEDS_INFO,
            ], true);
    }

    public function resubmit(User $user, ProjectRequest $request): bool
    {
        return $request->requester_id === $user->id && $request->status === ProjectRequestStatus::NEEDS_INFO;
    }

    private function role(User $user, Workspace $workspace): ?WorkspaceRole
    {
        return $workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->first()?->role;
    }
}
