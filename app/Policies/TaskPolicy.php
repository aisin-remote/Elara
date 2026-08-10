<?php

namespace App\Policies;

use App\Enums\ProjectMemberRole;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceMember;

class TaskPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return app(ProjectPolicy::class)->view($user, $project);
    }

    public function view(User $user, Task $task): bool
    {
        return app(ProjectPolicy::class)->view($user, $task->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->canMutateProject($user, $project);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->canMutateProject($user, $task->project);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function manageWorkflow(User $user, Project $project): bool
    {
        $workspaceRole = $this->workspaceMembership($user, $project)?->role;

        return in_array($workspaceRole, [WorkspaceRole::OWNER, WorkspaceRole::ADMIN], true)
            || $project->memberships()
                ->where('user_id', $user->id)
                ->where('role', ProjectMemberRole::MANAGER->value)
                ->exists();
    }

    private function canMutateProject(User $user, Project $project): bool
    {
        $workspaceRole = $this->workspaceMembership($user, $project)?->role;

        if (in_array($workspaceRole, [WorkspaceRole::OWNER, WorkspaceRole::ADMIN], true)) {
            return true;
        }

        return (bool) $workspaceRole?->canContribute()
            && $project->memberships()
                ->where('user_id', $user->id)
                ->whereIn('role', [ProjectMemberRole::MANAGER->value, ProjectMemberRole::MEMBER->value])
                ->exists();
    }

    private function workspaceMembership(User $user, Project $project): ?WorkspaceMember
    {
        return $project->workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->first();
    }
}
