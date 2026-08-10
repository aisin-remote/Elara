<?php

namespace App\Policies;

use App\Enums\WorkspaceMemberStatus;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Workspace;

class SupportTicketPolicy
{
    public function create(User $user, Workspace $workspace): bool
    {
        return $workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->exists();
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $ticket->requester_id === $user->id || $ticket->workspace->owner_id === $user->id;
    }
}
