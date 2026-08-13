<?php

namespace App\Policies;

use App\Enums\FeatureRequestStatus;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\FeatureRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OrganizationDirectory;

class FeatureRequestPolicy
{
    public function __construct(private readonly OrganizationDirectory $organization) {}

    /**
     * Requesters only. The delivery team does not file requests to itself — it creates work
     * on the board directly, and a request raised by IT would enter an approval queue whose
     * whole purpose is deciding whether IT should do it.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace)?->canUseRequestDesk() === true;
    }

    /** The approvals queue: everyone who can act on it, plus managers who watch it. */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), [
            WorkspaceRole::SUPERVISOR,
            WorkspaceRole::MANAGER,
            WorkspaceRole::ADMIN,
            WorkspaceRole::OWNER,
        ], true);
    }

    public function view(User $user, FeatureRequest $request): bool
    {
        $role = $this->role($user, $request->workspace);

        if ($request->requester_id === $user->id || $role?->canAccessDeliveryDesk()) {
            return true;
        }

        $profile = $this->organization->profile($user);

        return $profile !== null
            && $request->requester_department_external_id !== null
            && $profile['department_id'] === $request->requester_department_external_id;
    }

    /**
     * Supervisors and managers both decide feature requests: one signature is enough for a
     * change to a standing system, and whoever is available signs it. Project requests keep
     * the two-step order (supervisor first, then manager) — see ProjectRequestPolicy.
     */
    public function decide(User $user, FeatureRequest $request): bool
    {
        return in_array($this->role($user, $request->workspace), [
            WorkspaceRole::SUPERVISOR,
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

    public function departmentDecide(User $user, FeatureRequest $request): bool
    {
        return $request->status === FeatureRequestStatus::PENDING_DEPARTMENT
            && $request->requester_department_external_id !== null
            && $this->organization->canApproveDepartment($user, $request->requester_department_external_id);
    }

    /** The requester may pull it back while nobody has acted on it. */
    public function withdraw(User $user, FeatureRequest $request): bool
    {
        return $request->requester_id === $user->id && $request->status->isOpen()
            && in_array($request->status->value, ['draft', 'pending_department', 'pending_review', 'needs_info'], true);
    }

    /** Answering a needs-info question is the requester's job. */
    public function resubmit(User $user, FeatureRequest $request): bool
    {
        return $request->requester_id === $user->id;
    }

    private function role(User $user, Workspace $workspace): ?WorkspaceRole
    {
        return $workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->first()?->role;
    }
}
