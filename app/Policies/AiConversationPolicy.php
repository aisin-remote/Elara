<?php

namespace App\Policies;

use App\Enums\WorkspaceMemberStatus;
use App\Models\AiConversation;
use App\Models\User;

class AiConversationPolicy
{
    public function view(User $user, AiConversation $conversation): bool
    {
        return $conversation->user_id === $user->id
            && $conversation->workspace->memberships()
                ->where('user_id', $user->id)
                ->where('status', WorkspaceMemberStatus::ACTIVE->value)
                ->exists();
    }
}
