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
}
