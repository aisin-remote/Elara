<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Task\ArchiveTask;
use App\Enums\TaskStatusCategory;
use App\Http\Requests\Task\BulkTaskRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkTaskController extends Controller
{
    public function store(BulkTaskRequest $request, Project $project, ArchiveTask $archive): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $tasks = $project->tasks()->whereIn('public_id', $data['task_public_ids'])->get();

        if ($tasks->count() !== count($data['task_public_ids'])) {
            throw ValidationException::withMessages(['task_public_ids' => 'Every task must belong to this project.']);
        }

        $tasks->each(fn (Task $task) => $this->authorize('update', $task));

        DB::transaction(function () use ($data, $tasks, $project, $request, $archive): void {
            match ($data['action']) {
                'status' => $this->changeStatus($tasks, $project, $data['status_public_id']),
                'priority' => $tasks->each->update(['priority' => $data['priority'], 'version' => DB::raw('version + 1')]),
                'assignee' => $this->changeAssignee($tasks, $project, $request->user(), $data['assignee_public_id']),
                'archive' => $tasks->each(fn (Task $task) => $archive->archive($task, $request->user(), $request->ip())),
            };

            if ($data['action'] !== 'archive') {
                foreach ($tasks as $task) {
                    ActivityLog::record($project->workspace, $task, 'task.bulk_updated', $request->user(), ['action' => $data['action']], $request->ip());
                }
            }
        });

        return $this->success($request, ['updated' => $tasks->count()], 'Tasks updated.', route('app.projects.tasks', [$project->workspace, $project]));
    }

    private function changeStatus($tasks, Project $project, string $publicId): void
    {
        $status = TaskStatus::query()->active()->where('project_id', $project->id)->where('public_id', $publicId)->firstOrFail();
        $tasks->each(fn (Task $task) => $task->update([
            'status_id' => $status->id,
            'status_changed_at' => $task->status_id === $status->id ? $task->status_changed_at : now(),
            'completed_at' => $status->category === TaskStatusCategory::COMPLETED ? ($task->completed_at ?? now()) : null,
            'version' => DB::raw('version + 1'),
        ]));
    }

    private function changeAssignee($tasks, Project $project, User $actor, string $publicId): void
    {
        $user = $project->members()->where('users.public_id', $publicId)->firstOrFail();
        $tasks->each(fn (Task $task) => $task->assignees()->sync([$user->id => [
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
        ]]));
    }
}
