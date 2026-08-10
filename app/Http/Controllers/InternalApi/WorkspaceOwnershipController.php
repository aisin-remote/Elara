<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Workspace\TransferWorkspaceOwnership;
use App\Http\Requests\Workspace\TransferWorkspaceOwnershipRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class WorkspaceOwnershipController extends Controller
{
    public function store(TransferWorkspaceOwnershipRequest $request, Workspace $workspace, TransferWorkspaceOwnership $transfer): JsonResponse|RedirectResponse
    {
        $workspace = $transfer->handle($workspace, $request->user(), $request->string('member_public_id')->toString(), $request->ip());

        return $this->success($request, new WorkspaceResource($workspace), 'Ownership transferred.', route('app.workspaces.team', $workspace));
    }
}
