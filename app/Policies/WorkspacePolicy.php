<?php

namespace App\Policies;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $this->membership($user, $workspace) !== null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return in_array($this->membership($user, $workspace)?->role, [WorkspaceRole::OWNER, WorkspaceRole::ADMIN], true);
    }

    public function manageSettings(User $user, Workspace $workspace): bool
    {
        $membership = $this->membership($user, $workspace);

        return $this->update($user, $workspace)
            || ($workspace->organization_department_code === strtoupper((string) config('organization.it_department_code'))
                && $membership?->role->canContribute());
    }

    public function invite(User $user, Workspace $workspace): bool
    {
        return $this->update($user, $workspace);
    }

    /** Reference data follows the same Settings access boundary. */
    public function manageMasterData(User $user, Workspace $workspace): bool
    {
        return $this->manageSettings($user, $workspace);
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $this->update($user, $workspace);
    }

    public function transferOwnership(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    private function membership(User $user, Workspace $workspace): ?WorkspaceMember
    {
        return $workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->first();
    }
}
