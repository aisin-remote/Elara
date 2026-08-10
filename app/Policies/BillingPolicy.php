<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class BillingPolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    public function manage(User $user, Workspace $workspace): bool
    {
        return $this->view($user, $workspace);
    }
}
