<?php

namespace App\Policies;

use App\Enums\CheckpointStatus;
use App\Models\User;
use App\Models\ValidationCheckpoint;

class ValidationCheckpointPolicy
{
    /**
     * Only the person who raised the request answers for it. A PIC confirming their own work
     * on the requester's behalf is the exact loophole the checkpoint exists to close.
     */
    public function respond(User $user, ValidationCheckpoint $checkpoint): bool
    {
        return $checkpoint->requester_id === $user->id
            && $checkpoint->status === CheckpointStatus::OPEN;
    }

    public function view(User $user, ValidationCheckpoint $checkpoint): bool
    {
        return $checkpoint->requester_id === $user->id;
    }
}
