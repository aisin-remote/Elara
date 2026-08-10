<?php

namespace App\Http\Controllers\InternalApi;

use App\Enums\DependencyType;
use App\Http\Requests\Task\StoreTaskDependencyRequest;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Services\Planning\DateShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskDependencyController extends Controller
{
    public function store(
        StoreTaskDependencyRequest $request,
        Task $task,
        DateShiftService $shift,
    ): JsonResponse|RedirectResponse {
        $type = DependencyType::from($request->string('type')->toString() ?: DependencyType::FINISH_TO_START->value);
        $lagMinutes = (int) $request->integer('lag_minutes');

        $dependency = Task::query()
            ->where('workspace_id', $task->workspace_id)
            ->where('public_id', $request->string('dependency_public_id'))
            ->first();

        if (! $dependency) {
            throw ValidationException::withMessages(['dependency_public_id' => 'Choose another task from this workspace.']);
        }

        DB::transaction(function () use ($task, $dependency, $request, $type, $lagMinutes): void {
            Task::query()->where('workspace_id', $task->workspace_id)->lockForUpdate()->pluck('id');

            if ($task->is($dependency)) {
                throw ValidationException::withMessages(['dependency_public_id' => 'A task cannot depend on itself.']);
            }

            if ($this->createsCycle($task, $dependency)) {
                throw ValidationException::withMessages(['dependency_public_id' => 'That dependency would create a circular chain.']);
            }

            $task->dependencies()->syncWithoutDetaching([
                $dependency->id => [
                    'type' => $type->value,
                    'lag_minutes' => $lagMinutes,
                ],
            ]);

            ActivityLog::record($task->workspace, $task, 'task.dependency_added', $request->user(), [
                'dependency' => $dependency->title,
                'type' => $type->value,
                'lag_minutes' => $lagMinutes,
                'cross_project' => $dependency->project_id !== $task->project_id,
            ], $request->ip());
        });

        $shiftResult = $shift->shiftFrom($task->fresh(['dependencies', 'assignees']), $request->user(), $request->ip());

        return $this->success($request, [
            'task_public_id' => $task->public_id,
            'dependency_public_id' => $dependency->public_id,
            'type' => $type->value,
            'lag_minutes' => $lagMinutes,
            'is_blocked' => $task->fresh()->load('dependencies')->isBlocked(),
            'shifted' => $shiftResult['shifted'],
        ], 'Dependency added.', route('app.tasks.show', $task), 201);
    }

    public function destroy(Request $request, Task $task, Task $dependency): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $task);
        abort_unless($dependency->workspace_id === $task->workspace_id, 404);
        abort_unless($task->dependencies()->whereKey($dependency->id)->exists(), 404);

        $task->dependencies()->detach($dependency->id);
        ActivityLog::record($task->workspace, $task, 'task.dependency_removed', $request->user(), ['dependency' => $dependency->title], $request->ip());

        return $this->success($request, null, 'Dependency removed.', route('app.tasks.show', $task));
    }

    private function createsCycle(Task $task, Task $dependency): bool
    {
        $frontier = [$dependency->id];
        $visited = [];

        while ($frontier !== []) {
            if (in_array($task->id, $frontier, true)) {
                return true;
            }

            $visited = array_values(array_unique([...$visited, ...$frontier]));
            $frontier = DB::table('task_dependencies')
                ->whereIn('task_id', $frontier)
                ->whereNotIn('depends_on_task_id', $visited)
                ->pluck('depends_on_task_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return false;
    }
}
