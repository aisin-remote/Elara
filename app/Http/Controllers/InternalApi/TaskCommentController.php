<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Requests\Task\DeleteTaskCommentRequest;
use App\Http\Requests\Task\StoreTaskCommentRequest;
use App\Http\Requests\Task\UpdateTaskCommentRequest;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TaskCommentController extends Controller
{
    public function store(StoreTaskCommentRequest $request, Task $task, NotificationPreferenceService $notifications): JsonResponse|RedirectResponse
    {
        $comment = $task->comments()->create([
            'author_id' => $request->user()->id,
            'body' => $request->string('body')->toString(),
        ]);
        ActivityLog::record($task->workspace, $comment, 'task.comment_added', $request->user(), ipAddress: $request->ip());

        $task->loadMissing(['assignees', 'watchers', 'workspace']);
        $task->assignees->merge($task->watchers)->unique('id')->where('id', '!=', $request->user()->id)->each(fn (User $recipient) => $notifications->notify(
            $recipient,
            $task->workspace,
            'comment_mention',
            'New comment on '.$task->title,
            $request->user()->name.' commented: '.str($comment->body)->limit(120),
            route('app.tasks.show', $task),
            ['task_public_id' => $task->public_id, 'comment_public_id' => $comment->public_id],
        ));

        return $this->success($request, ['public_id' => $comment->public_id, 'body' => $comment->body], 'Comment added.', route('app.tasks.show', $task), 201);
    }

    public function update(UpdateTaskCommentRequest $request, TaskComment $comment): JsonResponse|RedirectResponse
    {
        $comment->update(['body' => $request->string('body')->toString(), 'edited_at' => now()]);

        return $this->success($request, ['public_id' => $comment->public_id, 'body' => $comment->body], 'Comment updated.', route('app.tasks.show', $comment->task));
    }

    public function destroy(DeleteTaskCommentRequest $request, TaskComment $comment): JsonResponse|RedirectResponse
    {
        $task = $comment->task;
        ActivityLog::record($task->workspace, $comment, 'task.comment_removed', $request->user(), ipAddress: $request->ip());
        $comment->delete();

        return $this->success($request, null, 'Comment removed.', route('app.tasks.show', $task));
    }
}
