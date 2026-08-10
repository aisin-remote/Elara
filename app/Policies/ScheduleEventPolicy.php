<?php

namespace App\Policies;

use App\Enums\WorkspaceMemberStatus;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class ScheduleEventPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $this->membership($user, $workspace) !== null;
    }

    public function view(User $user, ScheduleEvent $event): bool
    {
        return $event->project
            ? app(ProjectPolicy::class)->view($user, $event->project)
            : $this->viewAny($user, $event->workspace);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        $membership = $this->membership($user, $workspace);

        return (bool) $membership?->role->canContribute();
    }

    public function update(User $user, ScheduleEvent $event): bool
    {
        if ($event->project) {
            return app(TaskPolicy::class)->create($user, $event->project);
        }

        return $this->create($user, $event->workspace);
    }

    public function delete(User $user, ScheduleEvent $event): bool
    {
        return $this->update($user, $event);
    }

    private function membership(User $user, Workspace $workspace): ?WorkspaceMember
    {
        return $workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->first();
    }
}
