<?php

namespace App\Http\Controllers\InternalApi;

use App\Enums\SupportingTaskStatus;
use App\Http\Requests\Supporting\SaveSupportingTaskRequest;
use App\Http\Requests\Supporting\SupportingTaskMutationRequest;
use App\Models\ActivityLog;
use App\Models\SupportingTask;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SupportingTaskController extends Controller
{
    public function store(SaveSupportingTaskRequest $request, Workspace $workspace): JsonResponse|RedirectResponse
    {
        $task = DB::transaction(function () use ($request, $workspace): SupportingTask {
            $task = $workspace->supportingTasks()->create([
                ...$this->attributes($request, $workspace),
                'creator_id' => $request->user()->id,
                'completed_at' => $request->string('status')->toString() === SupportingTaskStatus::COMPLETED->value ? now() : null,
            ]);
            ActivityLog::record($workspace, $task, 'supporting.task_created', $request->user(), ipAddress: $request->ip());

            return $task;
        });

        return $this->success($request, $this->serialize($task), 'Supporting task created.', route('app.supporting.index', $workspace), 201);
    }

    public function update(SaveSupportingTaskRequest $request, SupportingTask $supportingTask): JsonResponse|RedirectResponse
    {
        $task = DB::transaction(function () use ($request, $supportingTask): SupportingTask {
            $status = SupportingTaskStatus::from($request->string('status')->toString());
            $supportingTask->update([
                ...$this->attributes($request, $supportingTask->workspace),
                'completed_at' => $status === SupportingTaskStatus::COMPLETED
                    ? ($supportingTask->completed_at ?? now())
                    : null,
            ]);
            ActivityLog::record($supportingTask->workspace, $supportingTask, 'supporting.task_updated', $request->user(), ipAddress: $request->ip());

            return $supportingTask->fresh(['assignee']);
        });

        return $this->success($request, $this->serialize($task), 'Supporting task updated.', route('app.supporting.index', $task->workspace));
    }

    public function destroy(SupportingTaskMutationRequest $request, SupportingTask $supportingTask): JsonResponse|RedirectResponse
    {
        $workspace = $supportingTask->workspace;

        DB::transaction(function () use ($request, $supportingTask, $workspace): void {
            ActivityLog::record($workspace, $supportingTask, 'supporting.task_archived', $request->user(), ipAddress: $request->ip());
            $supportingTask->delete();
        });

        return $this->success($request, null, 'Supporting task archived.', route('app.supporting.index', $workspace));
    }

    private function attributes(SaveSupportingTaskRequest $request, Workspace $workspace): array
    {
        $assignee = $request->string('assignee_public_id')->toString();

        return [
            'assignee_id' => $assignee === '' ? null : $workspace->memberships()
                ->whereHas('user', fn ($user) => $user->where('public_id', $assignee))
                ->value('user_id'),
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString() ?: null,
            'category' => $request->string('category')->toString(),
            'priority' => $request->string('priority')->toString(),
            'status' => $request->string('status')->toString(),
            'due_date' => $request->filled('due_date') ? $request->date('due_date')->toDateString() : null,
        ];
    }

    private function serialize(SupportingTask $task): array
    {
        return [
            'public_id' => $task->public_id,
            'title' => $task->title,
            'status' => $task->status->value,
            'assignee' => $task->assignee?->name,
        ];
    }
}
