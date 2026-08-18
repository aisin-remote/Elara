<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\File\StorePrivateFile;
use App\Http\Requests\Task\StoreTaskAttachmentRequest;
use App\Models\Task;
use App\Services\RequestTaskAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TaskAttachmentController extends Controller
{
    public function store(StoreTaskAttachmentRequest $request, Task $task, StorePrivateFile $storeFile, RequestTaskAccess $requestTasks): JsonResponse|RedirectResponse
    {
        $linkedRequest = $requestTasks->visibleRequest($request->user(), $task);
        $shared = $request->boolean('share_with_requester') && $linkedRequest !== null;
        $file = $storeFile->handle(
            $task->workspace,
            $request->user(),
            $request->file('attachment'),
            task: $task,
            metadata: $shared ? ['request_shared' => true] : [],
        );
        $redirect = ! $request->user()->can('update', $task) && $linkedRequest
            ? $requestTasks->detailUrl($linkedRequest)
            : route('app.tasks.show', $task);

        return $this->success($request, ['public_id' => $file->public_id, 'name' => $file->original_name], 'Attachment uploaded.', $redirect, 201);
    }
}
