<?php

namespace App\Http\Controllers\InternalApi;

use App\Enums\WorkspaceMemberStatus;
use App\Http\Requests\Workspace\DeactivateWorkspaceMemberRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceMemberRequest;
use App\Http\Resources\WorkspaceMemberResource;
use App\Models\ActivityLog;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class WorkspaceMemberController extends Controller
{
    public function update(UpdateWorkspaceMemberRequest $request, WorkspaceMember $member): JsonResponse|RedirectResponse
    {
        $member->update($request->validated());
        ActivityLog::record($member->workspace, $member, 'workspace.member_updated', $request->user(), [
            'role' => $member->role->value,
            'status' => $member->status->value,
        ], $request->ip());

        return $this->success($request, new WorkspaceMemberResource($member->load('user')), 'Member updated.', route('app.workspaces.team', $member->workspace));
    }

    public function destroy(DeactivateWorkspaceMemberRequest $request, WorkspaceMember $member): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($member, $request): void {
            $member->update(['status' => WorkspaceMemberStatus::INACTIVE]);
            DB::table('project_members')
                ->where('user_id', $member->user_id)
                ->whereIn('project_id', $member->workspace->projects()->withTrashed()->select('id'))
                ->delete();
            ActivityLog::record($member->workspace, $member, 'workspace.member_deactivated', $request->user(), ipAddress: $request->ip());
        });

        return $this->success($request, null, 'Member deactivated.', route('app.workspaces.team', $member->workspace));
    }
}
