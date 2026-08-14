<?php

namespace App\Policies;

use App\Enums\WorkspaceMemberStatus;
use App\Models\SupportingTask;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class SupportingTaskPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $this->membership($user, $workspace)?->role->canAccessDeliveryDesk() ?? false;
    }

    public function view(User $user, SupportingTask $task): bool
    {
        return $task->creator_id === $user->id || $this->viewAny($user, $task->workspace);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $this->membership($user, $workspace)?->role->canContribute() ?? false;
    }

    public function update(User $user, SupportingTask $task): bool
    {
        return $this->create($user, $task->workspace);
    }

    public function delete(User $user, SupportingTask $task): bool
    {
        return $this->update($user, $task);
    }

    private function membership(User $user, Workspace $workspace): ?WorkspaceMember
    {
        return $workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->first();
    }
}
