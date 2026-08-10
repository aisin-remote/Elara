<?php

namespace App\Http\Controllers\InternalApi;

use App\Enums\WorkspaceMemberStatus;
use App\Http\Requests\Project\RemoveProjectMemberRequest;
use App\Http\Requests\Project\StoreProjectMemberRequest;
use App\Http\Requests\Project\UpdateProjectMemberRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ProjectMemberController extends Controller
{
    public function store(StoreProjectMemberRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        $user = $this->workspaceUser($project, $request->string('member_public_id')->toString());
        $membership = $project->memberships()->updateOrCreate(
            ['user_id' => $user->id],
            ['role' => $request->string('role')->toString()],
        );
        ActivityLog::record($project->workspace, $membership, 'project.member_added', $request->user(), ['user_public_id' => $user->public_id], $request->ip());

        return $this->success($request, ['user_public_id' => $user->public_id, 'role' => $membership->role->value], 'Project member saved.', route('app.projects.show', $project), 201);
    }

    public function update(UpdateProjectMemberRequest $request, Project $project, string $member): JsonResponse|RedirectResponse
    {
        $membership = $this->projectMembership($project, $member);
        $this->ensureNotOwner($project, $membership->user_id);
        $membership->update($request->validated());
        ActivityLog::record($project->workspace, $membership, 'project.member_updated', $request->user(), ['user_public_id' => $member], $request->ip());

        return $this->success($request, ['user_public_id' => $member, 'role' => $membership->role->value], 'Project member updated.', route('app.projects.show', $project));
    }

    public function destroy(RemoveProjectMemberRequest $request, Project $project, string $member): JsonResponse|RedirectResponse
    {
        $membership = $this->projectMembership($project, $member);
        $this->ensureNotOwner($project, $membership->user_id);
        ActivityLog::record($project->workspace, $membership, 'project.member_removed', $request->user(), ['user_public_id' => $member], $request->ip());
        $membership->delete();

        return $this->success($request, null, 'Project member removed.', route('app.projects.show', $project));
    }

    private function workspaceUser(Project $project, string $publicId): User
    {
        return User::query()
            ->where('public_id', $publicId)
            ->whereHas('workspaceMemberships', fn ($query) => $query
                ->where('workspace_id', $project->workspace_id)
                ->where('status', WorkspaceMemberStatus::ACTIVE->value))
            ->firstOrFail();
    }

    private function projectMembership(Project $project, string $publicId)
    {
        return $project->memberships()
            ->whereHas('user', fn ($query) => $query->where('public_id', $publicId))
            ->firstOrFail();
    }

    private function ensureNotOwner(Project $project, int $userId): void
    {
        if ($project->owner_id === $userId) {
            throw ValidationException::withMessages(['member' => 'The project owner must remain a leader.']);
        }
    }
}
