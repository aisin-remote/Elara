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
        if ($project->isPersonal()) {
            return false;
        }

        $workspaceRole = $this->workspaceMembership($user, $project->workspace)?->role;

        return in_array($workspaceRole, [WorkspaceRole::OWNER, WorkspaceRole::ADMIN], true)
            || $project->memberships()->where('user_id', $user->id)->exists()
            // Reading work their own people are assigned to, even without being a member here.
            // Asked of the scope so the page and every list agree on one rule.
            || ($workspaceRole !== null && Project::query()->whereKey($project->getKey())->visibleTo($user)->exists());
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $this->workspaceMembership($user, $workspace)?->role->canContribute() ?? false;
    }

    public function update(User $user, Project $project): bool
    {
        if ($project->isPersonal()) {
            return false;
        }

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
