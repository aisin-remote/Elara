<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Requests\Task\UpdateTaskFieldRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Services\TaskFieldSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TaskFieldController extends Controller
{
    public function update(
        UpdateTaskFieldRequest $request,
        Project $project,
        string $field,
        TaskFieldSchema $schema,
    ): JsonResponse|RedirectResponse {
        $schema->updateSystemField(
            $project,
            $field,
            $request->string('name')->toString(),
            $request->boolean('visible'),
        );
        ActivityLog::record($project->workspace, $project, 'task_field.updated', $request->user(), [
            'field' => $field,
            'name' => $request->string('name')->toString(),
            'visible' => $request->boolean('visible'),
        ], $request->ip());

        return $this->success(
            $request,
            $schema->systemFields($project->fresh()),
            'Task field updated.',
            $project->taskListUrl(),
        );
    }
}
