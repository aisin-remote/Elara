<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Requests\Task\DeleteChecklistItemRequest;
use App\Http\Requests\Task\StoreChecklistItemRequest;
use App\Http\Requests\Task\UpdateChecklistItemRequest;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TaskChecklistController extends Controller
{
    public function store(StoreChecklistItemRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $item = $task->checklistItems()->create([
            'title' => $request->string('title')->toString(),
            'position' => ((int) $task->checklistItems()->max('position')) + 1024,
        ]);
        ActivityLog::record($task->workspace, $item, 'task.checklist_added', $request->user(), ipAddress: $request->ip());

        return $this->success($request, ['public_id' => $item->public_id, 'title' => $item->title], 'Checklist item added.', route('app.tasks.show', $task), 201);
    }

    public function update(UpdateChecklistItemRequest $request, TaskChecklistItem $item): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $item->update([
            ...$data,
            'completed_at' => $data['is_completed'] ? ($item->completed_at ?? now()) : null,
        ]);
        ActivityLog::record($item->task->workspace, $item, 'task.checklist_updated', $request->user(), ipAddress: $request->ip());

        return $this->success($request, ['public_id' => $item->public_id, ...$data], 'Checklist item updated.', route('app.tasks.show', $item->task));
    }

    public function destroy(DeleteChecklistItemRequest $request, TaskChecklistItem $item): JsonResponse|RedirectResponse
    {
        $task = $item->task;
        ActivityLog::record($task->workspace, $item, 'task.checklist_removed', $request->user(), ipAddress: $request->ip());
        $item->delete();

        return $this->success($request, null, 'Checklist item removed.', route('app.tasks.show', $task));
    }
}
