<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Planning\LogTaskTime;
use App\Http\Requests\Task\StoreTaskTimeEntryRequest;
use App\Models\Task;
use App\Models\TaskTimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskTimeEntryController extends Controller
{
    public function store(StoreTaskTimeEntryRequest $request, Task $task, LogTaskTime $log): JsonResponse|RedirectResponse
    {
        $entry = $log->handle($task, $request->user(), $request->validated(), $request->ip());

        return $this->success($request, [
            'public_id' => $entry->public_id,
            'minutes' => $entry->minutes,
            'logged_minutes' => $task->fresh()->loggedMinutes(),
        ], 'Time logged.', route('app.tasks.show', $task), 201);
    }

    public function destroy(Request $request, TaskTimeEntry $entry): JsonResponse|RedirectResponse
    {
        $task = $entry->task;
        $this->authorize('update', $task);
        $entry->delete();

        return $this->success(
            $request,
            ['logged_minutes' => $task->fresh()->loggedMinutes()],
            'Time entry removed.',
            route('app.tasks.show', $task),
        );
    }
}
