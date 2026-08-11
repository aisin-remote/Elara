<?php

namespace App\Policies;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceMember;

class WorkspaceMemberPolicy
{
    public function update(User $user, WorkspaceMember $member): bool
    {
        if ($member->user_id === $user->id || $member->role === WorkspaceRole::OWNER) {
            return false;
        }

        $actor = $member->workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->first();

        return $actor?->role === WorkspaceRole::OWNER
            || ($actor?->role === WorkspaceRole::ADMIN && $member->role !== WorkspaceRole::ADMIN);
    }

    public function delete(User $user, WorkspaceMember $member): bool
    {
        return $this->update($user, $member);
    }

    /**
     * Erasing the account, not merely closing the door on it. Kept separate from delete()
     * because deactivating access is reversible and this is not.
     *
     * Owner, admin, and manager may do it, and only to someone below them: a manager must not
     * be able to erase an admin, and nobody erases an owner while they still own something.
     */
    public function deleteAccount(User $user, WorkspaceMember $member): bool
    {
        if ($member->user_id === $user->id) {
            return false;
        }

        // Ownership is read from the workspace, not from a membership row. Not every workspace
        // gives its owner one — the ones created outside the invite flow have none — and an
        // owner locked out of their own workspace by a missing row is not a permission decision
        // anyone made.
        $rank = $member->workspace->owner_id === $user->id
            ? $this->rank(WorkspaceRole::OWNER)
            : $this->rank($member->workspace->memberships()
                ->where('user_id', $user->id)
                ->where('status', WorkspaceMemberStatus::ACTIVE->value)
                ->first()?->role);

        return $rank > 0 && $rank > $this->rank($member->role);
    }

    /** Higher outranks lower. Everything outside the three is zero and can neither act nor be protected. */
    private function rank(?WorkspaceRole $role): int
    {
        return match ($role) {
            WorkspaceRole::OWNER => 3,
            WorkspaceRole::ADMIN => 2,
            WorkspaceRole::MANAGER => 1,
            default => 0,
        };
    }
}
