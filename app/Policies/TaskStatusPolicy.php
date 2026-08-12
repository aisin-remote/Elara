<?php

namespace App\Policies;

use App\Models\TaskStatus;
use App\Models\User;

class TaskStatusPolicy
{
    public function update(User $user, TaskStatus $status): bool
    {
        return app(TaskPolicy::class)->manageWorkflow($user, $status->project);
    }

    public function delete(User $user, TaskStatus $status): bool
    {
        return $this->update($user, $status);
    }
}
