<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\File\DeleteFile;
use App\Actions\File\StorePrivateFile;
use App\Http\Requests\File\DeleteProjectFileRequest;
use App\Http\Requests\File\StoreProjectFileRequest;
use App\Http\Requests\File\UpdateProjectFileRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function store(StoreProjectFileRequest $request, Workspace $workspace, StorePrivateFile $store): JsonResponse|RedirectResponse
    {
        $project = $request->filled('project_public_id')
            ? Project::query()->where('workspace_id', $workspace->id)->where('public_id', $request->string('project_public_id'))->firstOrFail()
            : null;
        $task = $request->filled('task_public_id')
            ? Task::query()->where('workspace_id', $workspace->id)->where('public_id', $request->string('task_public_id'))->firstOrFail()
            : null;
        $file = $store->handle($workspace, $request->user(), $request->file('file'), $project, $task);
        $redirect = $file->project
            ? route('app.projects.files', [$workspace, $file->project])
            : route('app.workspaces.show', $workspace);

        return $this->success($request, $this->data($file), 'File uploaded.', $redirect, 201);
    }

    public function download(ProjectFile $file): StreamedResponse
    {
        $this->authorize('view', $file);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['Content-Type' => $file->mime_type]);
    }

    public function preview(ProjectFile $file): StreamedResponse
    {
        $this->authorize('view', $file);
        abort_unless($file->isPreviewable(), 415);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->response($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function update(UpdateProjectFileRequest $request, ProjectFile $file): JsonResponse|RedirectResponse
    {
        $attributes = ['original_name' => $request->string('original_name')->toString()];

        if ($request->exists('task_public_id')) {
            $task = $request->filled('task_public_id')
                ? Task::query()->where('workspace_id', $file->workspace_id)->where('public_id', $request->string('task_public_id'))->firstOrFail()
                : null;
            $attributes['task_id'] = $task?->id;
            $attributes['project_id'] = $file->project_id ?? $task?->project_id;
        }

        $file->update($attributes);
        $redirect = $file->project
            ? route('app.projects.files', [$file->workspace, $file->project])
            : route('app.workspaces.show', $file->workspace);

        return $this->success($request, $this->data($file->fresh()), 'File updated.', $redirect);
    }

    public function destroy(DeleteProjectFileRequest $request, ProjectFile $file, DeleteFile $delete): JsonResponse|RedirectResponse
    {
        $workspace = $file->workspace;
        $project = $file->project;
        $delete->handle($file);
        $redirect = $project ? route('app.projects.files', [$workspace, $project]) : route('app.workspaces.show', $workspace);

        return $this->success($request, null, 'File deleted.', $redirect);
    }

    private function data(ProjectFile $file): array
    {
        return [
            'public_id' => $file->public_id,
            'name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
        ];
    }
}
