<?php

namespace App\Policies;

use App\Enums\WorkspaceMemberStatus;
use App\Models\User;
use App\Models\Workspace;

class ReportPolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->exists();
    }
}
