<?php

namespace App\Policies;

use App\Enums\WorkspaceMemberStatus;
use App\Models\MeetingMinute;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class MeetingMinutePolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $this->membership($user, $workspace) !== null;
    }

    public function view(User $user, MeetingMinute $meetingMinute): bool
    {
        return $this->viewAny($user, $meetingMinute->workspace);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return (bool) $this->membership($user, $workspace)?->role->canContribute();
    }

    public function update(User $user, MeetingMinute $meetingMinute): bool
    {
        return $this->create($user, $meetingMinute->workspace);
    }

    public function delete(User $user, MeetingMinute $meetingMinute): bool
    {
        return $this->update($user, $meetingMinute);
    }

    private function membership(User $user, Workspace $workspace): ?WorkspaceMember
    {
        return $workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->first();
    }
}
