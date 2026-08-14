<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskPropertyType;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskProperty;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TaskDatabaseView
{
    public function __construct(
        private readonly TaskFieldSchema $fieldSchema,
        private readonly OrganizationDirectory $organization,
    ) {}

    public function data(
        Request $request,
        Workspace $workspace,
        Project $project,
        User $viewer,
        ?Feature $selectedFeature = null,
    ): array {
        $savedViews = $project->taskViews()->where('user_id', $viewer->id)->orderBy('name')->get();
        $selectedView = $savedViews->firstWhere('public_id', $request->string('saved_view')->toString());

        if ($selectedView) {
            foreach ($selectedView->parameters_json as $key => $value) {
                if (! $request->has($key)) {
                    $request->merge([$key => $value]);
                }
            }
        }

        $statuses = $project->taskStatuses()
            ->active()
            ->withCount(['tasks' => fn (Builder $tasks) => $tasks->withTrashed()])
            ->get();
        $tasks = $project->tasks()
            ->visibleTo($viewer)
            ->with([
                'status', 'category', 'assignees', 'milestone', 'propertyValues',
                'dependencies' => fn ($dependencies) => $dependencies->visibleTo($viewer),
            ])
            ->when($selectedFeature, fn (Builder $query, Feature $feature) => $query->where('feature_id', $feature->id))
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($request->string('priority')->toString(), fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($request->boolean('blocked'), fn (Builder $query) => $query->blocked())
            ->orderBy(
                in_array($request->string('sort')->toString(), ['position', 'title', 'due_at', 'updated_at'], true)
                    ? $request->string('sort')->toString()
                    : 'position',
                $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc',
            )
            ->paginate(50)
            ->withQueryString();

        $properties = $project->taskProperties()->active()->get();
        $systemFields = $this->fieldSchema->systemFields($project);
        $taskFields = $this->fieldSchema->visibleFields($project, $properties);
        $allTaskFields = $taskFields;
        $requestedFields = collect($request->input('fields', []))->filter()->values();
        if ($requestedFields->isNotEmpty()) {
            $taskFields = $taskFields->filter(fn (array $field) => $requestedFields->contains($field['key']))->values();
        }
        $groupByOptions = collect([['key' => 'status', 'name' => 'Workflow status']])
            ->concat($taskFields
                ->where('type', TaskPropertyType::SELECT->value)
                ->map(fn (array $field): array => [
                    'key' => $field['kind'] === 'system' ? $field['key'] : 'property:'.$field['property']->public_id,
                    'name' => $field['name'],
                ]))
            ->values();
        $requestedGroupBy = $request->string('group_by')->toString() ?: 'status';
        $groupBy = $groupByOptions->contains('key', $requestedGroupBy) ? $requestedGroupBy : 'status';
        $groupProperty = str_starts_with($groupBy, 'property:')
            ? $properties->firstWhere('public_id', substr($groupBy, strlen('property:')))
            : null;
        $visibleUserIds = $this->organization->taskMembers($viewer, $workspace)->pluck('id');

        return [
            'statuses' => $statuses,
            'tasks' => $tasks,
            'taskGroups' => $this->taskGroups($tasks->getCollection(), $statuses, $groupBy, $groupProperty),
            'groupBy' => $groupBy,
            'groupByOptions' => $groupByOptions,
            'properties' => $properties,
            'systemFields' => $systemFields,
            'taskFields' => $taskFields,
            'allTaskFields' => $allTaskFields,
            'archivedTasks' => $project->tasks()->onlyTrashed()
                ->visibleTo($viewer)
                ->when($selectedFeature, fn (Builder $query, Feature $feature) => $query->where('feature_id', $feature->id))
                ->with('status')->latest('archived_at')->get(),
            'selectedFeature' => $selectedFeature,
            'categories' => $workspace->taskCategories()->orderBy('name')->get(),
            'projectMembers' => $project->memberships()->whereIn('user_id', $visibleUserIds)->with('user')->get(),
            'priorities' => TaskPriority::cases(),
            'milestones' => $project->milestones()->get(),
            'features' => $project->features()->active()->orderBy('name')->get(),
            'savedViews' => $savedViews,
            'selectedSavedView' => $selectedView,
        ];
    }

    private function taskGroups(Collection $tasks, Collection $statuses, string $groupBy, ?TaskProperty $property): Collection
    {
        if ($groupBy === 'status') {
            return $statuses->map(fn ($status): array => [
                'key' => 'status:'.$status->public_id,
                'name' => $status->name,
                'color' => $status->color,
                'status' => $status,
                'tasks' => $tasks->where('status_id', $status->id)->values(),
                'defaults' => ['status_public_id' => $status->public_id],
            ]);
        }

        if ($groupBy === 'priority') {
            $colors = ['low' => '#64748b', 'medium' => '#0ea5e9', 'high' => '#f59e0b', 'urgent' => '#f43f5e'];

            return collect(TaskPriority::cases())->map(fn (TaskPriority $priority): array => [
                'key' => 'priority:'.$priority->value,
                'name' => $priority->label(),
                'color' => $colors[$priority->value],
                'status' => null,
                'tasks' => $tasks->where('priority', $priority)->values(),
                'defaults' => ['priority' => $priority->value],
            ]);
        }

        abort_unless($property, 404);
        $colors = ['#6366f1', '#0ea5e9', '#14b8a6', '#f59e0b', '#f43f5e', '#8b5cf6'];
        $options = collect($property->options_json ?? []);
        $value = fn (Task $task): mixed => $task->propertyValues
            ->firstWhere('task_property_id', $property->id)?->value_json;
        $groups = $options->values()->map(fn (string $option, int $index): array => [
            'key' => 'property:'.$property->public_id.':'.$index,
            'name' => $option,
            'color' => $colors[$index % count($colors)],
            'status' => null,
            'tasks' => $tasks->filter(fn (Task $task): bool => $value($task) === $option)->values(),
            'defaults' => ['property_values['.$property->public_id.']' => $option],
        ]);
        $withoutSelection = $tasks->reject(fn (Task $task): bool => $options->contains($value($task)))->values();

        return $withoutSelection->isEmpty() ? $groups : $groups->push([
            'key' => 'property:'.$property->public_id.':empty',
            'name' => 'No selection',
            'color' => '#94a3b8',
            'status' => null,
            'tasks' => $withoutSelection,
            'defaults' => ['property_values['.$property->public_id.']' => ''],
        ]);
    }
}
