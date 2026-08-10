<?php

namespace App\Http\Controllers\InternalApi;

use App\Enums\TaskStatusCategory;
use App\Http\Requests\Task\DeleteTaskStatusRequest;
use App\Http\Requests\Task\ReorderTaskStatusesRequest;
use App\Http\Requests\Task\StoreTaskStatusRequest;
use App\Http\Requests\Task\UpdateTaskStatusRequest;
use App\Http\Resources\TaskStatusResource;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskStatusController extends Controller
{
    public function store(StoreTaskStatusRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        if ($project->taskStatuses()->where('name', $request->string('name')->toString())->exists()) {
            throw ValidationException::withMessages(['name' => 'This project already has a status with that name.']);
        }

        $status = $project->taskStatuses()->create([
            ...$request->validated(),
            'position' => ((int) $project->taskStatuses()->max('position')) + 1024,
        ]);
        ActivityLog::record($project->workspace, $status, 'task_status.created', $request->user(), ipAddress: $request->ip());

        return $this->success($request, new TaskStatusResource($status), 'Status created.', route('app.projects.board', [$project->workspace, $project]), 201);
    }

    public function update(UpdateTaskStatusRequest $request, TaskStatus $status): JsonResponse|RedirectResponse
    {
        if ($status->project->taskStatuses()->whereKeyNot($status->id)->where('name', $request->string('name')->toString())->exists()) {
            throw ValidationException::withMessages(['name' => 'This project already has a status with that name.']);
        }

        $status->update($request->validated());
        ActivityLog::record($status->project->workspace, $status, 'task_status.updated', $request->user(), ipAddress: $request->ip());

        return $this->success($request, new TaskStatusResource($status), 'Status updated.', route('app.projects.board', [$status->project->workspace, $status->project]));
    }

    public function destroy(DeleteTaskStatusRequest $request, TaskStatus $status): JsonResponse|RedirectResponse
    {
        $replacement = $status->project->taskStatuses()
            ->active()
            ->whereKeyNot($status->id)
            ->where('public_id', $request->string('replacement_status_public_id')->toString())
            ->firstOrFail();

        DB::transaction(function () use ($status, $replacement, $request): void {
            $status->tasks()->update([
                'status_id' => $replacement->id,
                'completed_at' => $replacement->category === TaskStatusCategory::COMPLETED ? now() : null,
                'version' => DB::raw('version + 1'),
            ]);
            $status->update(['archived_at' => now()]);
            ActivityLog::record($status->project->workspace, $status, 'task_status.archived', $request->user(), ipAddress: $request->ip());
        });

        return $this->success($request, null, 'Status archived.', route('app.projects.board', [$status->project->workspace, $status->project]));
    }

    public function reorder(ReorderTaskStatusesRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        $ids = $request->validated('status_public_ids');
        $statuses = $project->taskStatuses()->active()->get();

        if ($statuses->pluck('public_id')->sort()->values()->all() !== collect($ids)->sort()->values()->all()) {
            throw ValidationException::withMessages(['status_public_ids' => 'Submit every active project status exactly once.']);
        }

        DB::transaction(function () use ($statuses, $ids): void {
            foreach ($ids as $index => $publicId) {
                $statuses->firstWhere('public_id', $publicId)->update(['position' => ($index + 1) * 1024]);
            }
        });

        return $this->success($request, TaskStatusResource::collection($project->taskStatuses()->active()->get()), 'Statuses reordered.', route('app.projects.board', [$project->workspace, $project]));
    }
}
