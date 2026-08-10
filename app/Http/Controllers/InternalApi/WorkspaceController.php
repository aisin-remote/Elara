<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Workspace\CreateWorkspace;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\ActivityLog;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class WorkspaceController extends Controller
{
    public function store(StoreWorkspaceRequest $request, CreateWorkspace $createWorkspace): JsonResponse|RedirectResponse
    {
        $workspace = $createWorkspace->handle($request->user(), $request->validated(), $request->ip());
        $request->session()->put('active_workspace_id', $workspace->id);

        return $this->success($request, new WorkspaceResource($workspace), 'Workspace created.', route('app.workspaces.show', $workspace), 201);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $workspace->update([
            ...Arr::except($data, 'week_start'),
            'settings_json' => [...($workspace->settings_json ?? []), 'week_start' => (int) $data['week_start']],
        ]);
        ActivityLog::record($workspace, $workspace, 'workspace.updated', $request->user(), ipAddress: $request->ip());

        return $this->success($request, new WorkspaceResource($workspace->fresh()), 'Workspace updated.', route('app.workspaces.settings', $workspace));
    }
}
