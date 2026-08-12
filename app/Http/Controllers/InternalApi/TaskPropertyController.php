<?php

namespace App\Http\Controllers\InternalApi;

use App\Enums\TaskPropertyType;
use App\Http\Requests\Task\SaveTaskPropertyRequest;
use App\Http\Requests\Task\TaskPropertyMutationRequest;
use App\Http\Requests\Task\UpdateTaskPropertyValueRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskProperty;
use App\Models\TaskPropertyValue;
use App\Services\TaskFieldSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TaskPropertyController extends Controller
{
    public function __construct(private readonly TaskFieldSchema $schema) {}

    public function store(SaveTaskPropertyRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->schema->ensureUniqueName($project, $request->string('name')->toString());

        $property = $project->taskProperties()->create([
            'name' => $request->string('name')->toString(),
            'type' => $request->enum('type', TaskPropertyType::class),
            'options_json' => $request->string('type')->toString() === TaskPropertyType::SELECT->value
                ? $request->validated('options')
                : null,
            'position' => ((int) $project->taskProperties()->max('position')) + 1024,
        ]);
        ActivityLog::record($project->workspace, $property, 'task_property.created', $request->user(), ipAddress: $request->ip());

        return $this->success($request, $property, 'Property created.', $this->taskListUrl($project), 201);
    }

    public function update(SaveTaskPropertyRequest $request, TaskProperty $property): JsonResponse|RedirectResponse
    {
        $this->schema->ensureUniqueName(
            $property->project,
            $request->string('name')->toString(),
            exceptProperty: $property,
        );
        $type = $request->enum('type', TaskPropertyType::class);

        DB::transaction(function () use ($property, $request, $type): void {
            if ($property->type !== $type) {
                $property->values()->delete();
            }

            $property->update([
                'name' => $request->string('name')->toString(),
                'type' => $type,
                'options_json' => $type === TaskPropertyType::SELECT ? $request->validated('options') : null,
            ]);
            ActivityLog::record($property->project->workspace, $property, 'task_property.updated', $request->user(), ipAddress: $request->ip());
        });

        return $this->success($request, $property->fresh(), 'Property updated.', $this->taskListUrl($property->project));
    }

    public function destroy(TaskPropertyMutationRequest $request, TaskProperty $property): JsonResponse|RedirectResponse
    {
        $property->update(['archived_at' => now()]);
        ActivityLog::record($property->project->workspace, $property, 'task_property.archived', $request->user(), ipAddress: $request->ip());

        return $this->success($request, null, 'Property archived.', $this->taskListUrl($property->project));
    }

    public function updateValue(UpdateTaskPropertyValueRequest $request, Task $task, TaskProperty $property): JsonResponse|RedirectResponse
    {
        $value = $request->validated('value');

        if ($value === null) {
            TaskPropertyValue::query()
                ->where('task_id', $task->id)
                ->where('task_property_id', $property->id)
                ->delete();
        } else {
            TaskPropertyValue::updateOrCreate(
                ['task_id' => $task->id, 'task_property_id' => $property->id],
                ['value_json' => $value],
            );
        }

        ActivityLog::record($task->workspace, $task, 'task_property_value.updated', $request->user(), [
            'property_public_id' => $property->public_id,
            'property_name' => $property->name,
        ], $request->ip());

        return $this->success($request, ['value' => $value], 'Property saved.', $this->taskListUrl($task->project));
    }

    private function taskListUrl(Project $project): string
    {
        return route('app.projects.tasks', [$project->workspace, $project]);
    }
}
