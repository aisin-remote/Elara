<?php

namespace App\Policies;

use App\Enums\WorkspaceMemberStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;

class ConversationPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->memberships()->where('user_id', $user->id)->where('status', WorkspaceMemberStatus::ACTIVE->value)->exists();
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->participantRecords()->where('user_id', $user->id)->exists()
            && $this->viewAny($user, $conversation->workspace);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        $membership = $workspace->memberships()->where('user_id', $user->id)->where('status', WorkspaceMemberStatus::ACTIVE->value)->first();

        return (bool) $membership?->role->canContribute();
    }

    public function send(User $user, Conversation $conversation): bool
    {
        $membership = $conversation->workspace->memberships()->where('user_id', $user->id)->where('status', WorkspaceMemberStatus::ACTIVE->value)->first();

        return $this->view($user, $conversation) && (bool) $membership?->role->canContribute();
    }

    public function markRead(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
