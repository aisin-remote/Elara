<?php

namespace App\Services;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\ApprovalDelegation;
use App\Models\User;
use App\Models\Workspace;

class ApprovalDelegationService
{
    public function fromRoles(User $delegate, Workspace $workspace, string $scope, array $roles): bool
    {
        return ApprovalDelegation::query()
            ->active()
            ->where('workspace_id', $workspace->id)
            ->where('delegate_id', $delegate->id)
            ->whereIn('scope', ['all', $scope])
            ->whereHas('delegator.workspaceMemberships', fn ($query) => $query
                ->where('workspace_id', $workspace->id)
                ->where('status', WorkspaceMemberStatus::ACTIVE->value)
                ->whereIn('role', collect($roles)->map(fn (WorkspaceRole $role) => $role->value)))
            ->exists();
    }

    public function forDepartment(User $delegate, Workspace $workspace, int $departmentId, OrganizationDirectory $organization): bool
    {
        return ApprovalDelegation::query()
            ->active()
            ->where('workspace_id', $workspace->id)
            ->where('delegate_id', $delegate->id)
            ->whereIn('scope', ['all', 'department'])
            ->with('delegator')
            ->get()
            ->contains(fn (ApprovalDelegation $delegation) => $organization->canApproveDepartment($delegation->delegator, $departmentId));
    }

    public function viewData(User $user, Workspace $workspace): array
    {
        return [
            'delegations' => ApprovalDelegation::where('workspace_id', $workspace->id)->where('delegator_id', $user->id)
                ->where('ends_at', '>=', now())->with('delegate')->orderBy('starts_at')->get(),
            'incomingDelegations' => ApprovalDelegation::where('workspace_id', $workspace->id)->where('delegate_id', $user->id)
                ->active()->with('delegator')->orderBy('ends_at')->get(),
            'delegationMembers' => $workspace->memberships()->active()->where('user_id', '!=', $user->id)->with('user')->get(),
        ];
    }
}
