<?php

namespace App\Policies;

use App\Enums\ProjectMemberRole;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\OrganizationDirectory;
use App\Services\RequestTaskAccess;

class TaskPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        if ($project->isPersonal()) {
            return $this->ownsPersonalSpace($user, $project);
        }

        return app(ProjectPolicy::class)->view($user, $project);
    }

    public function view(User $user, Task $task): bool
    {
        if ($task->project->isPersonal()) {
            return $this->ownsPersonalSpace($user, $task->project);
        }

        if (! app(ProjectPolicy::class)->view($user, $task->project)) {
            return false;
        }

        if (! config('organization.required')) {
            return true;
        }

        $visibleUserIds = app(OrganizationDirectory::class)->taskVisibility($user)[$task->workspace_id] ?? [];

        return $task->assignees()->whereIn('users.id', $visibleUserIds)->exists();
    }

    public function create(User $user, Project $project): bool
    {
        return $this->canMutateProject($user, $project);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->view($user, $task) && $this->canMutateProject($user, $task->project);
    }

    public function attachRequestDocument(User $user, Task $task): bool
    {
        return $this->update($user, $task)
            || app(RequestTaskAccess::class)->ownedRequest($user, $task) !== null;
    }

    public function viewRequestDocument(User $user, Task $task): bool
    {
        return app(RequestTaskAccess::class)->visibleRequest($user, $task) !== null;
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
        if ($project->isPersonal()) {
            return $this->ownsPersonalSpace($user, $project);
        }

        $workspaceRole = $this->workspaceMembership($user, $project)?->role;

        if (in_array($workspaceRole, [WorkspaceRole::OWNER, WorkspaceRole::ADMIN], true)
            || $project->memberships()
                ->where('user_id', $user->id)
                ->where('role', ProjectMemberRole::MANAGER->value)
                ->exists()) {
            return true;
        }

        if (! config('organization.required') || ! $workspaceRole?->canContribute()) {
            return false;
        }

        $subordinateIds = app(OrganizationDirectory::class)
            ->taskMembers($user, $project->workspace)
            ->reject(fn (User $member) => $member->is($user))
            ->pluck('id');

        return $subordinateIds->isNotEmpty()
            && $project->tasks()
                ->whereHas('assignees', fn ($assignees) => $assignees->whereIn('users.id', $subordinateIds))
                ->exists();
    }

    private function canMutateProject(User $user, Project $project): bool
    {
        if ($project->isPersonal()) {
            return $this->ownsPersonalSpace($user, $project);
        }

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

    private function ownsPersonalSpace(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id
            && $this->workspaceMembership($user, $project) !== null;
    }
}
