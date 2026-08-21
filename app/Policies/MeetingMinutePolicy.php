<?php

namespace App\Policies;

use App\Enums\MeetingMinutePublicationStatus;
use App\Enums\WorkspaceMemberStatus;
use App\Models\MeetingMinute;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class MeetingMinutePolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $this->membership($user, $workspace)?->role->canAccessDeliveryDesk() === true;
    }

    public function view(User $user, MeetingMinute $meetingMinute): bool
    {
        if ($this->viewAny($user, $meetingMinute->workspace) || $meetingMinute->creator_id === $user->id) {
            return true;
        }

        if ($meetingMinute->publication_status === MeetingMinutePublicationStatus::DRAFT) {
            return false;
        }

        return $meetingMinute->scheduleEvent?->attendees()->where('users.id', $user->id)->exists()
            || $meetingMinute->items()->where('pic_user_id', $user->id)->exists();
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return (bool) $this->membership($user, $workspace)?->role->canContribute();
    }

    public function update(User $user, MeetingMinute $meetingMinute): bool
    {
        return ! $meetingMinute->isLocked()
            && ($meetingMinute->creator_id === $user->id || $this->create($user, $meetingMinute->workspace));
    }

    public function delete(User $user, MeetingMinute $meetingMinute): bool
    {
        return $meetingMinute->publication_status === MeetingMinutePublicationStatus::DRAFT
            && $this->update($user, $meetingMinute);
    }

    public function managePublication(User $user, MeetingMinute $meetingMinute): bool
    {
        return $meetingMinute->creator_id === $user->id || $this->create($user, $meetingMinute->workspace);
    }

    private function membership(User $user, Workspace $workspace): ?WorkspaceMember
    {
        return $workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->first();
    }
}
