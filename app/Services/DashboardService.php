<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\SupportingTask;
use App\Models\Task;
use App\Models\TaskProperty;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class DashboardService
{
    public function forWorkspace(Workspace $workspace, User $user, array $filters = []): array
    {
        $period = $this->period($workspace, $filters);
        $current = $this->snapshot($workspace, $user, $filters, $period['from_utc'], $period['to_utc']);
        $previous = $this->snapshot($workspace, $user, $filters, $period['previous_from_utc'], $period['previous_to_utc']);

        return [
            'period' => $this->serializePeriod($period),
            'kpis' => collect($current)->map(fn (int $value, string $key) => [
                'value' => $value,
                'previous' => $previous[$key],
                'delta' => $value - $previous[$key],
            ])->all(),
            'trend' => $this->trend($workspace, $user, $filters, $period),
            'distribution' => $this->distribution($workspace, $user, $filters, $period),
            'member_task_heatmap' => $this->memberTaskHeatmap($workspace, $user, $filters, $period),
            'recent_activity' => $this->recentActivity($workspace, $user, $filters, $period),
            'meetings' => $this->meetings($workspace, $user, $period['timezone']),
            'members' => $this->members($workspace),
            'gantt' => $this->gantt($workspace, $user, $filters, $period['timezone']),
            'today_tasks' => $this->todayTasks($workspace, $user, $period['timezone']),
        ];
    }

    private function todayTasks(Workspace $workspace, User $user, string $timezone): array
    {
        $todayStart = CarbonImmutable::now($timezone)->startOfDay()->utc();
        $todayEnd = CarbonImmutable::now($timezone)->endOfDay()->utc();

        return $this->taskQuery($workspace, $user)
            ->where(function (Builder $query) use ($todayStart, $todayEnd) {
                $query->whereBetween('due_at', [$todayStart, $todayEnd])
                    ->orWhere(function (Builder $q) use ($todayStart) {
                        $q->where('due_at', '<', $todayStart)
                            ->whereHas('status', fn (Builder $s) => $s->whereNotIn('category', [
                                TaskStatusCategory::COMPLETED->value,
                                TaskStatusCategory::CANCELLED->value,
                            ]));
                    });
            })
            ->whereHas('assignees', fn (Builder $q) => $q->where('users.id', $user->id))
            ->with(['project', 'assignees', 'checklistItems'])
            ->withCount(['comments', 'files', 'checklistItems as checklist_total', 'checklistItems as checklist_completed' => fn (Builder $q) => $q->where('is_completed', true)])
            ->orderBy('priority', 'desc')
            ->limit(2)
            ->get()
            ->map(function (Task $task) use ($todayStart, $timezone) {
                $progress = $task->checklist_total > 0
                    ? (int) round(($task->checklist_completed / $task->checklist_total) * 100)
                    : (in_array($task->status->category, [TaskStatusCategory::COMPLETED->value, TaskStatusCategory::CANCELLED->value]) ? 100 : 0);

                return [
                    'public_id' => $task->public_id,
                    'title' => $task->title,
                    'project_public_id' => $task->project->public_id,
                    'description' => Str::limit($task->description ?? 'No description', 80),
                    'priority' => $task->priority->value,
                    'priority_label' => $task->priority->label(),
                    'priority_color' => match ($task->priority->value) {
                        'urgent' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300',
                        'high' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
                        'medium' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300',
                        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                    },
                    'progress' => $progress,
                    // The query also pulls unfinished tasks from before today, so the card has to say so.
                    'is_overdue' => $task->completed_at === null && $task->due_at !== null && $task->due_at->lt($todayStart),
                    'due_label' => $task->due_at
                        ? CarbonImmutable::instance($task->due_at)->setTimezone($timezone)->format('M j')
                        : null,
                    'comments_count' => $task->comments_count,
                    'files_count' => $task->files_count,
                    'assignees' => $task->assignees->take(3)->map(fn (User $assignee) => [
                        'name' => $assignee->name,
                        'has_avatar' => filled($assignee->avatar_path),
                        'public_id' => $assignee->public_id,
                    ])->all(),
                ];
            })->all();
    }

    public function period(Workspace $workspace, array $filters): array
    {
        $timezone = in_array($workspace->timezone, timezone_identifiers_list(), true) ? $workspace->timezone : 'UTC';
        $today = CarbonImmutable::now($timezone);
        $from = isset($filters['from'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'], $timezone)->startOfDay()
            : $today->subDays(29)->startOfDay();
        $to = isset($filters['to'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'], $timezone)->endOfDay()
            : $today->endOfDay();
        $days = $from->diffInDays($to) + 1;

        return [
            'timezone' => $timezone,
            'from' => $from,
            'to' => $to,
            'from_utc' => $from->utc(),
            'to_utc' => $to->utc(),
            'previous_from_utc' => $from->subDays($days)->utc(),
            'previous_to_utc' => $from->subSecond()->utc(),
            'days' => $days,
        ];
    }

    public function taskQuery(Workspace $workspace, User $user, array $filters = []): Builder
    {
        return Task::query()
            ->visibleTo($user)
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->when($filters['project_public_id'] ?? null, fn (Builder $query, string $publicId) => $query
                ->whereHas('project', fn (Builder $project) => $project->where('public_id', $publicId)))
            ->when($filters['member_public_id'] ?? null, fn (Builder $query, string $publicId) => $query
                ->whereHas('assignees', fn (Builder $assignee) => $assignee->where('users.public_id', $publicId)))
            ->when($filters['status_public_id'] ?? null, fn (Builder $query, string $publicId) => $query
                ->whereHas('status', fn (Builder $status) => $status->where('public_id', $publicId)));
    }

    public function activeInPeriod(Builder $query, CarbonImmutable $fromUtc, CarbonImmutable $toUtc): Builder
    {
        return $query
            ->where('created_at', '<=', $toUtc)
            ->where(fn (Builder $active) => $active
                ->whereNull('completed_at')
                ->orWhere('completed_at', '>=', $fromUtc));
    }

    private function snapshot(Workspace $workspace, User $user, array $filters, CarbonImmutable $fromUtc, CarbonImmutable $toUtc): array
    {
        $base = $this->taskQuery($workspace, $user, $filters);
        $active = $this->activeInPeriod(clone $base, $fromUtc, $toUtc);
        $asOf = $toUtc->min(CarbonImmutable::now('UTC'));

        return [
            'total' => (clone $active)->count(),
            'in_progress' => (clone $active)->whereHas('status', fn (Builder $status) => $status
                ->where('category', TaskStatusCategory::IN_PROGRESS->value))->count(),
            'overdue' => (clone $active)
                ->whereNotNull('due_at')
                ->where('due_at', '<', $asOf)
                ->whereHas('status', fn (Builder $status) => $status->whereNotIn('category', [
                    TaskStatusCategory::COMPLETED->value,
                    TaskStatusCategory::CANCELLED->value,
                ]))->count(),
            'completed' => (clone $base)->whereBetween('completed_at', [$fromUtc, $toUtc])->count(),
        ];
    }

    private function trend(Workspace $workspace, User $user, array $filters, array $period): array
    {
        $unit = $period['days'] <= 45 ? 'day' : ($period['days'] <= 180 ? 'week' : 'month');
        [$buckets, $labels] = $this->buckets($period['from'], $period['to'], $unit);
        $series = [
            'created' => array_fill_keys(array_keys($buckets), 0),
            'completed' => array_fill_keys(array_keys($buckets), 0),
            'overdue' => array_fill_keys(array_keys($buckets), 0),
        ];
        $base = $this->taskQuery($workspace, $user, $filters);

        (clone $base)->whereBetween('created_at', [$period['from_utc'], $period['to_utc']])
            ->get(['created_at'])->each(function (Task $task) use (&$series, $period, $unit): void {
                $key = $this->bucketKey(CarbonImmutable::instance($task->created_at)->setTimezone($period['timezone']), $period['from'], $unit);
                isset($series['created'][$key]) && $series['created'][$key]++;
            });

        (clone $base)->whereBetween('completed_at', [$period['from_utc'], $period['to_utc']])
            ->get(['completed_at'])->each(function (Task $task) use (&$series, $period, $unit): void {
                $key = $this->bucketKey(CarbonImmutable::instance($task->completed_at)->setTimezone($period['timezone']), $period['from'], $unit);
                isset($series['completed'][$key]) && $series['completed'][$key]++;
            });

        (clone $base)->whereBetween('due_at', [$period['from_utc'], $period['to_utc']])
            ->whereHas('status', fn (Builder $status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value))
            ->where(fn (Builder $late) => $late->whereNull('completed_at')->orWhereColumn('completed_at', '>', 'due_at'))
            ->get(['due_at'])->each(function (Task $task) use (&$series, $period, $unit): void {
                $key = $this->bucketKey(CarbonImmutable::instance($task->due_at)->setTimezone($period['timezone']), $period['from'], $unit);
                isset($series['overdue'][$key]) && $series['overdue'][$key]++;
            });

        return [
            'unit' => $unit,
            'labels' => array_values($labels),
            'created' => array_values($series['created']),
            'completed' => array_values($series['completed']),
            'overdue' => array_values($series['overdue']),
        ];
    }

    private function distribution(Workspace $workspace, User $user, array $filters, array $period): array
    {
        $type = ($filters['distribution'] ?? 'status') === 'priority' ? 'priority' : 'status';
        $query = $this->activeInPeriod($this->taskQuery($workspace, $user, $filters), $period['from_utc'], $period['to_utc']);

        if ($type === 'priority') {
            $counts = (clone $query)->selectRaw('priority, COUNT(*) as aggregate')->groupBy('priority')->pluck('aggregate', 'priority');

            return $this->serializeDistribution(
                $type,
                collect(TaskPriority::cases())->map(fn (TaskPriority $priority) => $priority->label())->values()->all(),
                collect(TaskPriority::cases())->map(fn (TaskPriority $priority) => (int) ($counts[$priority->value] ?? 0))->values()->all(),
                ['#94a3b8', '#38bdf8', '#f59e0b', '#f43f5e'],
            );
        }

        // Every project owns its own status rows, so grouping by status_id repeats each
        // name once per project. Fold them into the shared categories instead.
        $counts = (clone $query)->selectRaw('status_id, COUNT(*) as aggregate')->groupBy('status_id')->pluck('aggregate', 'status_id');
        $categoryOf = TaskStatus::query()->whereIn('id', $counts->keys())->pluck('category', 'id');
        $totals = collect(TaskStatusCategory::cases())->mapWithKeys(fn (TaskStatusCategory $case) => [$case->value => 0])->all();

        foreach ($counts as $statusId => $aggregate) {
            // pluck() applies the model cast, so this arrives as an enum, not a string.
            $category = $categoryOf[$statusId] ?? null;
            if ($category instanceof TaskStatusCategory) {
                $totals[$category->value] += (int) $aggregate;
            }
        }

        return $this->serializeDistribution(
            $type,
            collect(TaskStatusCategory::cases())->map(fn (TaskStatusCategory $case) => $case->label())->values()->all(),
            array_values($totals),
            ['#94a3b8', '#38bdf8', '#f59e0b', '#10b981', '#f43f5e'],
        );
    }

    /** Rows carry the share so the view stays a plain loop. */
    private function serializeDistribution(string $type, array $labels, array $values, array $colors): array
    {
        $total = array_sum($values);

        return [
            'type' => $type,
            'labels' => $labels,
            'values' => $values,
            'colors' => $colors,
            'total' => $total,
            'rows' => collect($labels)->map(fn (string $label, int $index) => [
                'label' => $label,
                'value' => $values[$index],
                'color' => $colors[$index] ?? '#94a3b8',
                'share' => $total ? (int) round($values[$index] / $total * 100) : 0,
            ])->sortByDesc('value')->values()->all(),
        ];
    }

    private function members(Workspace $workspace): array
    {
        return $workspace->memberships()
            ->active()
            ->with('user:id,public_id,first_name,last_name,avatar_path,job_title')
            ->orderBy('id')
            ->limit(6)
            ->get()
            ->map(fn (WorkspaceMember $membership) => [
                'name' => $membership->user->name,
                'job_title' => $membership->user->job_title,
                'role' => $membership->role->label(),
                'public_id' => $membership->user->public_id,
                'has_avatar' => filled($membership->user->avatar_path),
                'url' => route('app.workspaces.team.show', [$workspace, $membership]),
            ])->all();
    }

    private function memberTaskHeatmap(Workspace $workspace, User $user, array $filters, array $period): array
    {
        $members = $workspace->memberships()->active()
            ->with('user:id,public_id,first_name,last_name,avatar_path')
            ->get()->sortBy(fn ($membership) => $membership->user->name);
        $rows = [];

        foreach ($members as $membership) {
            $rows[$membership->user_id] = [
                'public_id' => $membership->user->public_id,
                'name' => $membership->user->name,
                'has_avatar' => filled($membership->user->avatar_path),
                'open' => 0,
                'completed' => 0,
            ];
        }

        $assignments = $this->taskQuery($workspace, $user, $filters)
            ->whereBetween('tasks.due_at', [$period['from_utc'], $period['to_utc']])
            ->whereHas('status', fn (Builder $status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value))
            ->join('task_assignees', 'tasks.id', '=', 'task_assignees.task_id')
            ->get(['tasks.id', 'tasks.due_at', 'task_assignees.user_id', 'tasks.completed_at']);

        foreach ($assignments as $assignment) {
            if (isset($rows[$assignment->user_id])) {
                if ($assignment->completed_at) {
                    $rows[$assignment->user_id]['completed']++;
                } else {
                    $rows[$assignment->user_id]['open']++;
                }
            }
        }

        return [
            'members' => array_values($rows),
            'total_tasks' => $assignments->pluck('id')->unique()->count(),
        ];
    }

    private function recentActivity(Workspace $workspace, User $user, array $filters, array $period): array
    {
        $membership = $workspace->memberships()->where('user_id', $user->id)->first();
        $isWorkspaceManager = in_array($membership?->role?->value, [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value], true);
        $projectIds = Project::query()->visibleTo($user)->where('workspace_id', $workspace->id)->select('id');
        $taskIds = $this->taskQuery($workspace, $user, $filters)->select('tasks.id');
        $propertyIds = TaskProperty::query()->whereIn('project_id', clone $projectIds)->select('id');
        $statusIds = TaskStatus::query()->whereIn('project_id', clone $projectIds)->select('id');
        $query = $workspace->activityLogs()->with(['actor:id,public_id,first_name,last_name,avatar_path', 'subject'])
            ->whereBetween('created_at', [$period['from_utc'], $period['to_utc']])
            ->where(fn (Builder $visible) => $visible
                ->whereNotIn('subject_type', [
                    (new Task)->getMorphClass(),
                    (new TaskProperty)->getMorphClass(),
                    (new TaskStatus)->getMorphClass(),
                ])
                ->orWhere(fn (Builder $task) => $task
                    ->where('subject_type', (new Task)->getMorphClass())
                    ->whereIn('subject_id', $taskIds))
                ->orWhere(fn (Builder $property) => $property
                    ->where('subject_type', (new TaskProperty)->getMorphClass())
                    ->whereIn('subject_id', $propertyIds))
                ->orWhere(fn (Builder $status) => $status
                    ->where('subject_type', (new TaskStatus)->getMorphClass())
                    ->whereIn('subject_id', $statusIds)));

        if (! $isWorkspaceManager) {
            $supportingTaskIds = SupportingTask::query()->where('workspace_id', $workspace->id)->select('id');
            $eventIds = ScheduleEvent::query()->visibleTo($user)->where('workspace_id', $workspace->id)->select('id');
            $query->where(fn (Builder $visible) => $visible
                ->where(fn (Builder $projects) => $projects->where('subject_type', (new Project)->getMorphClass())->whereIn('subject_id', $projectIds))
                ->orWhere(fn (Builder $tasks) => $tasks->where('subject_type', (new Task)->getMorphClass())->whereIn('subject_id', $taskIds))
                ->orWhere(fn (Builder $supporting) => $supporting->where('subject_type', (new SupportingTask)->getMorphClass())->whereIn('subject_id', $supportingTaskIds))
                ->orWhere(fn (Builder $events) => $events->where('subject_type', (new ScheduleEvent)->getMorphClass())->whereIn('subject_id', $eventIds)));
        }

        return $query->latest('created_at')->limit(3)->get()->map(fn (ActivityLog $activity) => [
            'actor' => $activity->actor?->name ?? 'System',
            'actor_public_id' => $activity->actor?->public_id,
            'actor_has_avatar' => filled($activity->actor?->avatar_path),
            // headline() + lcfirst() in the view used to render "task Created".
            'action' => str($activity->action)->replace(['.', '_'], ' ')->lower()->toString(),
            'subject' => $this->activitySubject($activity, $workspace),
            'occurred_at' => CarbonImmutable::instance($activity->created_at)->setTimezone($period['timezone'])->toIso8601String(),
            'relative' => $activity->created_at->diffForHumans(),
        ])->all();
    }

    /** Null when the subject was deleted or has no page of its own. */
    private function activitySubject(ActivityLog $activity, Workspace $workspace): ?array
    {
        $subject = $activity->subject;

        return match (true) {
            $subject instanceof Task => ['label' => $subject->title, 'url' => route('app.tasks.show', $subject)],
            $subject instanceof SupportingTask => ['label' => $subject->title, 'url' => route('app.supporting.index', [$workspace, 'search' => $subject->title])],
            $subject instanceof Project => ['label' => $subject->name, 'url' => route('app.projects.show', $subject)],
            $subject instanceof ScheduleEvent => ['label' => $subject->title, 'url' => route('app.schedule.index', $workspace)],
            default => null,
        };
    }

    private function meetings(Workspace $workspace, User $user, string $timezone): array
    {
        return $workspace->scheduleEvents()->visibleTo($user)
            ->where('start_at', '>=', now())
            ->where(fn (Builder $mine) => $mine
                ->where('creator_id', $user->id)
                ->orWhereHas('attendees', fn (Builder $attendee) => $attendee->where('users.id', $user->id)))
            ->with('project:id,public_id,name,color')
            ->orderBy('start_at')->limit(5)->get()
            ->map(fn (ScheduleEvent $event) => [
                'public_id' => $event->public_id,
                'title' => $event->title,
                'project' => $event->project?->name,
                'project_color' => $event->project?->color,
                'starts_at' => CarbonImmutable::instance($event->start_at)->setTimezone($timezone)->toIso8601String(),
                'date_label' => CarbonImmutable::instance($event->start_at)->setTimezone($timezone)->format('D, M j'),
                'time_label' => CarbonImmutable::instance($event->start_at)->setTimezone($timezone)->format('H:i'),
                'meeting_url' => $event->meeting_url,
            ])->all();
    }

    private function gantt(Workspace $workspace, User $user, array $filters, string $timezone): array
    {
        $scale = $filters['gantt_scale'] ?? 'monthly';
        $today = CarbonImmutable::now($timezone);
        [$from, $to] = match ($scale) {
            'weekly' => [$today->startOfWeek()->subWeeks(2), $today->endOfWeek()->addWeeks(9)],
            'monthly' => [$today->startOfMonth()->subMonthsNoOverflow(2), $today->endOfMonth()->addMonthsNoOverflow(9)],
            'yearly' => [$today->startOfYear()->subYear(), $today->endOfYear()->addYears(3)],
            default => [$today->startOfDay()->subDays(3), $today->endOfDay()->addDays(10)],
        };
        $rangeSeconds = max(1, $from->diffInSeconds($to));
        $projects = Project::query()->delivery()->visibleTo($user)
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->whereNotNull('start_date')
            ->whereNotNull('due_date')
            ->whereDate('start_date', '<=', $to->format('Y-m-d'))
            ->whereDate('due_date', '>=', $from->format('Y-m-d'))
            ->when($filters['project_public_id'] ?? null, fn (Builder $query, string $publicId) => $query->where('public_id', $publicId))
            ->with('members:id,public_id,first_name,last_name,avatar_path')
            ->withCount([
                'tasks as gantt_total_tasks_count' => fn (Builder $query) => $query
                    ->whereNull('archived_at')
                    ->whereHas('status', fn (Builder $status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value)),
                'tasks as gantt_completed_tasks_count' => fn (Builder $query) => $query
                    ->whereNull('archived_at')
                    ->whereNotNull('completed_at')
                    ->whereHas('status', fn (Builder $status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value)),
            ])
            ->orderBy('start_date')
            ->limit(12)
            ->get()
            ->map(function (Project $project) use ($from, $rangeSeconds, $timezone): array {
                $start = CarbonImmutable::createFromFormat('!Y-m-d', $project->start_date->format('Y-m-d'), $timezone)->startOfDay();
                $due = CarbonImmutable::createFromFormat('!Y-m-d', $project->due_date->format('Y-m-d'), $timezone)->endOfDay();
                $visibleStart = $start->max($from);
                $visibleEnd = $due->min($from->addSeconds($rangeSeconds));
                $left = round($from->diffInSeconds($visibleStart) / $rangeSeconds * 100, 3);
                $width = max(1.25, round($visibleStart->diffInSeconds($visibleEnd) / $rangeSeconds * 100, 3));
                $total = (int) $project->gantt_total_tasks_count;

                return [
                    'public_id' => $project->public_id,
                    'name' => $project->name,
                    'color' => $project->color ?: '#2eb0fb',
                    'start' => $start->format('Y-m-d'),
                    'due' => $due->format('Y-m-d'),
                    'date_label' => $start->format('M j').' – '.$due->format('M j, Y'),
                    'progress' => $total ? (int) round($project->gantt_completed_tasks_count / $total * 100) : 0,
                    'left' => $left,
                    'width' => min(100 - $left, $width),
                    'members' => $project->members->take(3)->map(fn (User $member) => [
                        'public_id' => $member->public_id,
                        'name' => $member->name,
                        'has_avatar' => filled($member->avatar_path),
                    ])->values()->all(),
                ];
            })->all();

        $ticks = [];
        $cursor = $from;
        while ($cursor->lte($to)) {
            $ticks[] = [
                'label' => match ($scale) {
                    'daily' => $cursor->format('D, M j'),
                    'weekly' => $cursor->format('M j'),
                    'monthly' => $cursor->format('M Y'),
                    default => $cursor->format('Y'),
                },
                'left' => round($from->diffInSeconds($cursor) / $rangeSeconds * 100, 3),
            ];
            $cursor = match ($scale) {
                'daily' => $cursor->addDay(),
                'weekly' => $cursor->addWeek(),
                'monthly' => $cursor->addMonthNoOverflow(),
                default => $cursor->addYear(),
            };
        }

        return [
            'scale' => $scale,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'ticks' => $ticks,
            'today_position' => $today->betweenIncluded($from, $to)
                ? round($from->diffInSeconds($today) / $rangeSeconds * 100, 3)
                : null,
            'min_width' => match ($scale) {
                'daily' => 1080,
                'weekly' => 960,
                'monthly' => 900,
                default => 760,
            },
            'projects' => $projects,
        ];
    }

    private function buckets(CarbonImmutable $from, CarbonImmutable $to, string $unit): array
    {
        $buckets = [];
        $labels = [];
        $cursor = $from->startOfDay();

        while ($cursor->lte($to)) {
            $key = $this->bucketKey($cursor, $from, $unit);
            $buckets[$key] = 0;
            $labels[$key] = match ($unit) {
                'day' => $cursor->format('M j'),
                'week' => 'Week '.$cursor->format('M j'),
                default => $cursor->format('M Y'),
            };
            $cursor = match ($unit) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                default => $cursor->addMonthNoOverflow()->startOfMonth(),
            };
        }

        return [$buckets, $labels];
    }

    private function bucketKey(CarbonImmutable $date, CarbonImmutable $from, string $unit): string
    {
        return match ($unit) {
            'day' => $date->format('Y-m-d'),
            'week' => 'week-'.intdiv($from->startOfDay()->diffInDays($date->startOfDay()), 7),
            default => $date->format('Y-m'),
        };
    }

    private function serializePeriod(array $period): array
    {
        return [
            'from' => $period['from']->format('Y-m-d'),
            'to' => $period['to']->format('Y-m-d'),
            'label' => $period['from']->format('M j').' – '.$period['to']->format('M j, Y'),
            'timezone' => $period['timezone'],
            'days' => $period['days'],
        ];
    }
}
