<?php

namespace App\Policies;

use App\Models\MeetingMinuteItem;
use App\Models\User;

class MeetingMinuteItemPolicy
{
    public function view(User $user, MeetingMinuteItem $item): bool
    {
        return $user->can('view', $item->meetingMinute);
    }

    public function update(User $user, MeetingMinuteItem $item): bool
    {
        return $item->pic_user_id === $user->id || $user->can('update', $item->meetingMinute);
    }
}
