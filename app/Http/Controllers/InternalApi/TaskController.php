<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\File\StorePrivateFile;
use App\Actions\Task\ArchiveTask;
use App\Actions\Task\CreateTask;
use App\Actions\Task\DuplicateTask;
use App\Actions\Task\UpdateTask;
use App\Http\Requests\Task\DuplicateTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\TaskMutationRequest;
use App\Http\Requests\Task\UpdateInlineTaskFieldRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('viewAny', [Task::class, $project]);
        $tasks = $project->tasks()
            ->with(['status', 'category', 'assignees', 'milestone', 'dependencies'])
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->orderBy('position')
            ->paginate(min(100, max(1, $request->integer('per_page', 50))));

        return TaskResource::collection($tasks)->response();
    }

    public function store(StoreTaskRequest $request, Project $project, CreateTask $createTask, StorePrivateFile $storeFile): JsonResponse|RedirectResponse
    {
        $task = $createTask->handle($project, $request->user(), $request->validated(), $request->ip());

        foreach ($request->file('attachments', []) as $upload) {
            $storeFile->handle($task->workspace, $request->user(), $upload, task: $task);
        }

        return $this->success($request, new TaskResource($task->load(['workspace', 'project', 'status', 'category', 'milestone', 'assignees', 'dependencies', 'propertyValues', 'files'])), 'Task created.', route('app.tasks.show', $task), 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return (new TaskResource($task->load(['workspace', 'project', 'status', 'category', 'assignees', 'milestone', 'dependencies', 'checklistItems', 'files'])))->response();
    }

    public function update(UpdateTaskRequest $request, Task $task, UpdateTask $updateTask, StorePrivateFile $storeFile): JsonResponse|RedirectResponse
    {
        $task = $updateTask->handle($task, $request->user(), $request->safe()->except(['version', 'attachments']), $request->integer('version'), $request->ip());

        if (! $task) {
            return $this->conflict($request, $request->route('task')->fresh()->version);
        }

        foreach ($request->file('attachments', []) as $upload) {
            $storeFile->handle($task->workspace, $request->user(), $upload, task: $task);
        }

        return $this->success($request, new TaskResource($task), 'Task updated.', route('app.tasks.show', $task));
    }

    public function updateField(UpdateInlineTaskFieldRequest $request, Task $task, UpdateTask $updateTask): JsonResponse
    {
        $task->loadMissing(['status', 'category', 'milestone', 'assignees']);
        $validated = $request->validated();
        $field = $validated['field'];
        $data = [
            'status_public_id' => $task->status->public_id,
            'category_public_id' => $task->category?->public_id,
            'milestone_public_id' => $task->milestone?->public_id,
            'assignee_public_ids' => $task->assignees->pluck('public_id')->all(),
        ];

        if ($field === 'assignees') {
            $data['assignee_public_ids'] = $validated['value'];
        } else {
            $data[$field] = $validated['value'];
        }

        $updated = $updateTask->handle($task, $request->user(), $data, $request->integer('version'), $request->ip());

        if (! $updated) {
            return response()->json([
                'message' => 'The task has changed.',
                'server_version' => $request->route('task')->fresh()->version,
            ], 409);
        }

        return response()->json([
            'data' => new TaskResource($updated),
            'message' => 'Task updated.',
        ]);
    }

    public function destroy(TaskMutationRequest $request, Task $task, ArchiveTask $archive): JsonResponse|RedirectResponse
    {
        $project = $task->project;
        $archive->archive($task, $request->user(), $request->ip());

        return $this->success($request, null, 'Task archived.', route('app.projects.tasks', [$project->workspace, $project]));
    }

    public function restore(TaskMutationRequest $request, Task $task, ArchiveTask $archive): JsonResponse|RedirectResponse
    {
        $task = $archive->restore($task, $request->user(), $request->ip());

        return $this->success($request, new TaskResource($task), 'Task restored.', route('app.tasks.show', $task));
    }

    public function duplicate(DuplicateTaskRequest $request, Task $task, DuplicateTask $duplicate): JsonResponse|RedirectResponse
    {
        $copy = $duplicate->handle($task->load(['assignees', 'watchers', 'checklistItems']), $request->user(), $request->ip());

        return $this->success($request, new TaskResource($copy), 'Task duplicated.', route('app.tasks.show', $copy), 201);
    }

    private function conflict(Request $request, int $serverVersion): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'The task has changed.', 'server_version' => $serverVersion], 409);
        }

        return back()->withInput()->withErrors(['version' => 'This task changed in another session. Refresh and try again.']);
    }
}
