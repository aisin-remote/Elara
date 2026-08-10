<?php

namespace App\Policies;

use App\Models\IntegrationConnection;
use App\Models\User;
use App\Models\Workspace;

class IntegrationConnectionPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->can('manageSettings', $workspace);
    }

    public function connect(User $user, Workspace $workspace): bool
    {
        return $this->viewAny($user, $workspace);
    }

    public function update(User $user, IntegrationConnection $connection): bool
    {
        return $this->viewAny($user, $connection->workspace);
    }

    public function delete(User $user, IntegrationConnection $connection): bool
    {
        return $this->update($user, $connection);
    }
}
