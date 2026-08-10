<?php

namespace App\Policies;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\TaskBreakdown;
use App\Models\User;

class TaskBreakdownPolicy
{
    /**
     * The person who will do the work, or someone senior enough to have approved it. A
     * viewer or a requester never accepts a plan on the PIC's behalf — the whole point of
     * the human step is that the person who pays for a wrong estimate sees it first.
     */
    public function accept(User $user, TaskBreakdown $breakdown): bool
    {
        $subject = $breakdown->subject;

        if ($subject?->assignee_id === $user->id) {
            return true;
        }

        return in_array($this->role($user, $breakdown), [
            WorkspaceRole::SUPERVISOR,
            WorkspaceRole::MANAGER,
            WorkspaceRole::ADMIN,
            WorkspaceRole::OWNER,
        ], true);
    }

    /** Regenerating and discarding are the same decision as accepting, made differently. */
    public function manage(User $user, TaskBreakdown $breakdown): bool
    {
        return $this->accept($user, $breakdown);
    }

    private function role(User $user, TaskBreakdown $breakdown): ?WorkspaceRole
    {
        return $breakdown->workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->first()?->role;
    }
}
