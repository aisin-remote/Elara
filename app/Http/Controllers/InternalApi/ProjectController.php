<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Project\ArchiveProject;
use App\Actions\Project\CreateProject;
use App\Actions\Project\UpdateProject;
use App\Http\Requests\Project\ProjectMutationRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ProjectController extends Controller
{
    public function store(StoreProjectRequest $request, Workspace $workspace, CreateProject $createProject): JsonResponse|RedirectResponse
    {
        $project = $createProject->handle($workspace, $request->user(), $request->validated(), $request->ip());

        return $this->success($request, new ProjectResource($project), 'Project created.', route('app.projects.show', $project), 201);
    }

    public function update(UpdateProjectRequest $request, Project $project, UpdateProject $updateProject): JsonResponse|RedirectResponse
    {
        $data = $request->safe()->except('version');
        $project = $updateProject->handle($project, $request->user(), $data, $request->integer('version'), $request->ip());

        if (! $project) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This project changed in another session. Refresh and try again.',
                    'server_version' => Project::withTrashed()->findOrFail($request->route('project')->id)->version,
                ], 409);
            }

            return back()->withInput()->withErrors(['version' => 'This project changed in another session. Refresh and try again.']);
        }

        return $this->success($request, new ProjectResource($project), 'Project updated.', route('app.projects.show', $project));
    }

    public function destroy(ProjectMutationRequest $request, Project $project, ArchiveProject $archive): JsonResponse|RedirectResponse
    {
        $workspace = $project->workspace;
        $archive->archive($project, $request->user(), $request->ip());

        return $this->success($request, null, 'Project archived.', route('app.projects.index', $workspace));
    }

    public function restore(ProjectMutationRequest $request, Project $project, ArchiveProject $archive): JsonResponse|RedirectResponse
    {
        $project = $archive->restore($project, $request->user(), $request->ip());

        return $this->success($request, new ProjectResource($project), 'Project restored.', route('app.projects.show', $project));
    }
}
