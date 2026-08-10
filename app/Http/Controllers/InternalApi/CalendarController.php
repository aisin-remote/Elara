<?php

namespace App\Http\Controllers\InternalApi;

use App\Enums\TaskPriority;
use App\Http\Requests\Schedule\CalendarRequest;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    public function index(CalendarRequest $request, Workspace $workspace): JsonResponse
    {
        $start = Carbon::parse($request->validated('start'), $workspace->timezone)->utc();
        $end = Carbon::parse($request->validated('end'), $workspace->timezone)->utc();
        $projectId = $request->filled('project_public_id')
            ? Project::query()->where('workspace_id', $workspace->id)->where('public_id', $request->string('project_public_id'))->value('id')
            : null;

        $tasks = Task::query()
            ->visibleTo($request->user())
            ->where('workspace_id', $workspace->id)
            ->when($projectId, fn (Builder $query) => $query->where('project_id', $projectId))
            ->where(function (Builder $range) use ($start, $end) {
                $range->whereBetween('start_at', [$start, $end])
                    ->orWhereBetween('due_at', [$start, $end])
                    ->orWhere(fn (Builder $spans) => $spans->where('start_at', '<=', $start)->where('due_at', '>=', $end));
            })
            ->with(['project', 'status', 'category', 'assignees'])
            ->get()
            ->map(fn (Task $task) => $this->taskEvent($request, $task));

        $events = ScheduleEvent::query()
            ->visibleTo($request->user())
            ->where('workspace_id', $workspace->id)
            ->when($projectId, fn (Builder $query) => $query->where('project_id', $projectId))
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->with(['project', 'attendees'])
            ->get()
            ->map(fn (ScheduleEvent $event) => $this->scheduleEvent($request, $event));

        return response()->json([
            'data' => $tasks->concat($events)->sortBy('start')->values(),
            'meta' => ['timezone' => $workspace->timezone],
        ]);
    }

    private function taskEvent(CalendarRequest $request, Task $task): array
    {
        $colors = [
            TaskPriority::LOW->value => '#0ea5e9',
            TaskPriority::MEDIUM->value => '#6366f1',
            TaskPriority::HIGH->value => '#f97316',
            TaskPriority::URGENT->value => '#ef4444',
        ];

        return [
            'id' => 'task-'.$task->public_id,
            'type' => 'task',
            'title' => $task->title,
            'start' => ($task->start_at ?? $task->due_at)?->toIso8601String(),
            'end' => ($task->due_at ?? $task->start_at)?->toIso8601String(),
            'color' => $task->category?->color ?? $colors[$task->priority->value],
            'url' => route('app.tasks.show', $task),
            'can_update' => $request->user()->can('update', $task),
            'mutation_url' => route('internal.tasks.update', $task),
            'mutation' => [
                'title' => $task->title,
                'description' => $task->description,
                'status_public_id' => $task->status->public_id,
                'category_public_id' => $task->category?->public_id,
                'priority' => $task->priority->value,
                'start_at' => $task->start_at?->toIso8601String(),
                'due_at' => $task->due_at?->toIso8601String(),
                'estimate_minutes' => $task->estimate_minutes,
                'assignee_public_ids' => $task->assignees->pluck('public_id')->all(),
                'version' => $task->version,
            ],
        ];
    }

    private function scheduleEvent(CalendarRequest $request, ScheduleEvent $event): array
    {
        return [
            'id' => 'event-'.$event->public_id,
            'type' => 'event',
            'title' => $event->title,
            'start' => $event->start_at->toIso8601String(),
            'end' => $event->end_at->toIso8601String(),
            'color' => $event->color ?? $event->project?->color ?? '#4f46e5',
            'meeting_url' => $event->meeting_url,
            'can_update' => $request->user()->can('update', $event),
            'mutation_url' => route('internal.schedule-events.update', $event),
            'mutation' => [
                'title' => $event->title,
                'description' => $event->description,
                'project_public_id' => $event->project?->public_id,
                'start_at' => $event->start_at->toIso8601String(),
                'end_at' => $event->end_at->toIso8601String(),
                'color' => $event->color,
                'meeting_url' => $event->meeting_url,
                'attendee_public_ids' => $event->attendees->pluck('public_id')->all(),
                'version' => $event->version,
            ],
        ];
    }
}
