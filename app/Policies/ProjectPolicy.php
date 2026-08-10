<?php

namespace App\Policies;

use App\Enums\ProjectMemberRole;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class ProjectPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $this->workspaceMembership($user, $workspace) !== null;
    }

    public function view(User $user, Project $project): bool
    {
        $workspaceRole = $this->workspaceMembership($user, $project->workspace)?->role;

        return in_array($workspaceRole, [WorkspaceRole::OWNER, WorkspaceRole::ADMIN], true)
            || $project->memberships()->where('user_id', $user->id)->exists();
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return in_array($this->workspaceMembership($user, $workspace)?->role, [WorkspaceRole::OWNER, WorkspaceRole::ADMIN], true);
    }

    public function update(User $user, Project $project): bool
    {
        $workspaceRole = $this->workspaceMembership($user, $project->workspace)?->role;

        return in_array($workspaceRole, [WorkspaceRole::OWNER, WorkspaceRole::ADMIN], true)
            || $project->memberships()
                ->where('user_id', $user->id)
                ->where('role', ProjectMemberRole::MANAGER->value)
                ->exists();
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function restore(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    private function workspaceMembership(User $user, Workspace $workspace): ?WorkspaceMember
    {
        return $workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->first();
    }
}
