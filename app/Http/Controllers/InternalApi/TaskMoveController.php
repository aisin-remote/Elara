<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Task\MoveTask;
use App\Http\Requests\Task\MoveTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;

class TaskMoveController extends Controller
{
    public function store(MoveTaskRequest $request, Task $task, MoveTask $move): JsonResponse
    {
        $moved = $move->handle($task, $request->user(), $request->validated(), $request->ip());

        if (! $moved) {
            return response()->json(['message' => 'The task has changed.', 'server_version' => $task->fresh()->version], 409);
        }

        return response()->json(['data' => new TaskResource($moved), 'message' => 'Task moved.']);
    }
}
