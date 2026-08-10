<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\File\StorePrivateFile;
use App\Http\Requests\Task\StoreTaskAttachmentRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TaskAttachmentController extends Controller
{
    public function store(StoreTaskAttachmentRequest $request, Task $task, StorePrivateFile $storeFile): JsonResponse|RedirectResponse
    {
        $file = $storeFile->handle($task->workspace, $request->user(), $request->file('attachment'), task: $task);

        return $this->success($request, ['public_id' => $file->public_id, 'name' => $file->original_name], 'Attachment uploaded.', route('app.tasks.show', $task), 201);
    }
}
