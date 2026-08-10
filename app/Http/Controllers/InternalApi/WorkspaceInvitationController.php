<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Workspace\AcceptInvitation;
use App\Actions\Workspace\InviteWorkspaceMember;
use App\Http\Requests\Workspace\InvitationDecisionRequest;
use App\Http\Requests\Workspace\InviteWorkspaceMemberRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class WorkspaceInvitationController extends Controller
{
    public function store(InviteWorkspaceMemberRequest $request, Workspace $workspace, InviteWorkspaceMember $invite): JsonResponse|RedirectResponse
    {
        $invitation = $invite->handle(
            $workspace,
            $request->user(),
            $request->string('email')->toString(),
            $request->string('role')->toString(),
            $request->ip(),
        );

        return $this->success($request, [
            'public_id' => $invitation->public_id,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expires_at,
        ], 'Invitation sent.', route('app.workspaces.team', $workspace), 201);
    }

    public function accept(InvitationDecisionRequest $request, string $token, AcceptInvitation $accept): JsonResponse|RedirectResponse
    {
        $workspace = $accept->handle($request->user(), $token, $request->ip());
        $request->session()->put('active_workspace_id', $workspace->id);

        return $this->success($request, ['workspace_public_id' => $workspace->public_id], 'Invitation accepted.', route('app.workspaces.show', $workspace));
    }

    public function reject(InvitationDecisionRequest $request, string $token, AcceptInvitation $accept): JsonResponse|RedirectResponse
    {
        $accept->reject($request->user(), $token, $request->ip());

        return $this->success($request, null, 'Invitation declined.', route('app.dashboard'));
    }
}
