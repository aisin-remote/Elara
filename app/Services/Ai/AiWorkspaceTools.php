<?php

namespace App\Services\Ai;

use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectRequestStatus;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Planning\CriticalPathAnalyzer;
use App\Services\Planning\ForecastHealthService;
use App\Services\Planning\PortfolioService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Permission-aware, read-only access to Orbitra data for Ask AI. */
class AiWorkspaceTools
{
    /** @return array<int, array<string, mixed>> */
    public function definitions(): array
    {
        $object = fn (array $properties, array $required): array => [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];

        return [
            $this->tool('list_my_tasks', 'List tasks assigned to the current user.', $object([
                'status' => ['type' => 'string', 'enum' => ['open', 'overdue', 'completed']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
            ], ['status', 'limit'])),
            $this->tool('get_project_health', 'Get progress, schedule health, and task totals for one visible project.', $object([
                'project_public_id' => ['type' => 'string'],
            ], ['project_public_id'])),
            $this->tool('get_schedule', 'Get visible workspace events in a date range of at most 31 days.', $object([
                'from' => ['type' => 'string', 'description' => 'Date in YYYY-MM-DD format.'],
                'to' => ['type' => 'string', 'description' => 'Date in YYYY-MM-DD format.'],
            ], ['from', 'to'])),
            $this->tool('get_team_workload', 'Get open task and estimate totals for members of one visible project.', $object([
                'project_public_id' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
            ], ['project_public_id', 'limit'])),
            $this->tool('list_requests', 'List feature and project requests visible to the current user.', $object([
                'status' => ['type' => 'string', 'description' => 'Use all or an Orbitra request status such as approved or in_progress.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
            ], ['status', 'limit'])),
            $this->tool('search_workspace', 'Search visible projects, tasks, feature requests, and project requests by title.', $object([
                'query' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
            ], ['query', 'limit'])),
            $this->tool('get_portfolio_health', 'Get portfolio forecast summary and per-project health for visible work.', $object([
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
            ], ['limit'])),
            $this->tool('list_critical_tasks', 'List critical-path tasks for one visible project.', $object([
                'project_public_id' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
            ], ['project_public_id', 'limit'])),
        ];
    }

    /** @return array<string, mixed> */
    public function call(string $name, array $arguments, Workspace $workspace, User $user): array
    {
        return match ($name) {
            'list_my_tasks' => $this->listMyTasks($arguments, $workspace, $user),
            'get_project_health' => $this->projectHealth($arguments, $workspace, $user),
            'get_schedule' => $this->schedule($arguments, $workspace, $user),
            'get_team_workload' => $this->teamWorkload($arguments, $workspace, $user),
            'list_requests' => $this->requests($arguments, $workspace, $user),
            'search_workspace' => $this->search($arguments, $workspace, $user),
            'get_portfolio_health' => $this->portfolioHealth($arguments, $workspace, $user),
            'list_critical_tasks' => $this->criticalTasks($arguments, $workspace, $user),
            default => ['error' => 'Unknown read-only tool.'],
        };
    }

    private function tool(string $name, string $description, array $parameters): array
    {
        return [
            'type' => 'function',
            'name' => $name,
            'description' => $description,
            'strict' => true,
            'parameters' => $parameters,
        ];
    }

    private function listMyTasks(array $arguments, Workspace $workspace, User $user): array
    {
        $validated = $this->validate($arguments, [
            'status' => ['required', Rule::in(['open', 'overdue', 'completed'])],
            'limit' => ['required', 'integer', 'between:1,20'],
        ]);

        if (isset($validated['error'])) {
            return $validated;
        }

        $tasks = Task::query()
            ->visibleTo($user)
            ->where('tasks.workspace_id', $workspace->id)
            ->whereHas('assignees', fn (Builder $query) => $query->where('users.id', $user->id))
            ->with(['project:id,public_id,name,type', 'status:id,name,category'])
            ->when($validated['status'] === 'completed', fn (Builder $query) => $query->whereNotNull('completed_at'))
            ->when($validated['status'] === 'open', fn (Builder $query) => $query->whereNull('completed_at'))
            ->when($validated['status'] === 'overdue', fn (Builder $query) => $query->whereNull('completed_at')->where('due_at', '<', now()))
            ->orderByRaw('due_at is null, due_at asc')
            ->limit($validated['limit'])
            ->get();

        return ['tasks' => $tasks->map(fn (Task $task) => $this->taskData($task))->all()];
    }

    private function projectHealth(array $arguments, Workspace $workspace, User $user): array
    {
        $validated = $this->validate($arguments, ['project_public_id' => ['required', 'string', 'size:26']]);
        if (isset($validated['error'])) {
            return $validated;
        }

        $project = $this->project($validated['project_public_id'], $workspace, $user);
        if (! $project) {
            return ['error' => 'Project not found or not visible to this user.'];
        }

        $progress = $project->taskProgress($user);
        $forecast = app(ForecastHealthService::class)->forProject($project);

        return [
            'project' => [
                'name' => $project->name,
                'status' => $project->status->value,
                'start_date' => $project->start_date?->toDateString(),
                'due_date' => $project->due_date?->toDateString(),
                'progress' => $progress,
                'schedule_health' => $project->scheduleHealth($progress['percentage']),
                'forecast' => [
                    'state' => $forecast['state'],
                    'label' => $forecast['label'],
                    'blocked' => $forecast['blocked'],
                    'critical' => $forecast['critical'],
                    'projected_finish' => $forecast['projected_finish'],
                    'reason' => $forecast['reason'],
                ],
                'url' => route('app.projects.show', $project),
            ],
        ];
    }

    private function schedule(array $arguments, Workspace $workspace, User $user): array
    {
        $validated = $this->validate($arguments, [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        if (isset($validated['error'])) {
            return $validated;
        }

        $from = CarbonImmutable::parse($validated['from'], $workspace->timezone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($validated['to'], $workspace->timezone)->endOfDay()->utc();
        if ($from->diffInDays($to) > 31) {
            return ['error' => 'The schedule range may not exceed 31 days.'];
        }

        $events = ScheduleEvent::query()
            ->visibleTo($user)
            ->where('workspace_id', $workspace->id)
            ->where('start_at', '<=', $to)
            ->where('end_at', '>=', $from)
            ->with(['project:id,public_id,name', 'attendees:id,first_name,last_name'])
            ->orderBy('start_at')
            ->limit(50)
            ->get();

        return ['events' => $events->map(fn (ScheduleEvent $event) => [
            'title' => $event->title,
            'project' => $event->project?->name,
            'start_at' => $event->start_at->timezone($workspace->timezone)->toIso8601String(),
            'end_at' => $event->end_at->timezone($workspace->timezone)->toIso8601String(),
            'attendees' => $event->attendees->pluck('name')->all(),
            'url' => route('app.schedule.index', ['workspace' => $workspace, 'date' => $event->start_at->timezone($workspace->timezone)->toDateString()]),
        ])->all()];
    }

    private function teamWorkload(array $arguments, Workspace $workspace, User $user): array
    {
        $validated = $this->validate($arguments, [
            'project_public_id' => ['required', 'string', 'size:26'],
            'limit' => ['required', 'integer', 'between:1,20'],
        ]);
        if (isset($validated['error'])) {
            return $validated;
        }

        $project = $this->project($validated['project_public_id'], $workspace, $user);
        if (! $project) {
            return ['error' => 'Project not found or not visible to this user.'];
        }

        $members = $project->members()->limit($validated['limit'])->get(['users.id', 'users.first_name', 'users.last_name']);
        $totals = Task::query()
            ->visibleTo($user)
            ->where('tasks.project_id', $project->id)
            ->whereNull('tasks.completed_at')
            ->join('task_assignees', 'task_assignees.task_id', '=', 'tasks.id')
            ->selectRaw('task_assignees.user_id, count(*) as open_tasks, coalesce(sum(tasks.estimate_minutes), 0) as estimate_minutes, sum(case when tasks.due_at < ? then 1 else 0 end) as overdue_tasks', [now()])
            ->groupBy('task_assignees.user_id')
            ->get()
            ->keyBy('user_id');

        return [
            'project' => $project->name,
            'members' => $members->map(function (User $member) use ($totals): array {
                $total = $totals->get($member->id);

                return [
                    'name' => $member->name,
                    'open_tasks' => (int) ($total?->open_tasks ?? 0),
                    'overdue_tasks' => (int) ($total?->overdue_tasks ?? 0),
                    'estimate_minutes' => (int) ($total?->estimate_minutes ?? 0),
                ];
            })->all(),
        ];
    }

    private function requests(array $arguments, Workspace $workspace, User $user): array
    {
        $statuses = array_unique(array_merge(
            ['all'],
            array_column(FeatureRequestStatus::cases(), 'value'),
            array_column(ProjectRequestStatus::cases(), 'value'),
        ));
        $validated = $this->validate($arguments, [
            'status' => ['required', Rule::in($statuses)],
            'limit' => ['required', 'integer', 'between:1,20'],
        ]);
        if (isset($validated['error'])) {
            return $validated;
        }

        $each = max(1, (int) ceil($validated['limit'] / 2));
        $featureRequests = FeatureRequest::query()
            ->visibleTo($user, $workspace)
            ->where('workspace_id', $workspace->id)
            ->when($validated['status'] !== 'all', fn (Builder $query) => $query->where('status', $validated['status']))
            ->latest()
            ->limit($each)
            ->get()
            ->map(fn (FeatureRequest $request) => [
                'type' => 'feature_request',
                'title' => $request->title,
                'status' => $request->status->value,
                'updated_at' => $request->updated_at->toIso8601String(),
                'url' => route('app.approvals.show', [$workspace, $request]),
            ]);
        $projectRequests = ProjectRequest::query()
            ->visibleTo($user, $workspace)
            ->where('workspace_id', $workspace->id)
            ->when($validated['status'] !== 'all', fn (Builder $query) => $query->where('status', $validated['status']))
            ->latest()
            ->limit($each)
            ->get()
            ->map(fn (ProjectRequest $request) => [
                'type' => 'project_request',
                'title' => $request->title,
                'status' => $request->status->value,
                'updated_at' => $request->updated_at->toIso8601String(),
                'url' => route('app.approvals.projects.show', [$workspace, $request]),
            ]);

        return ['requests' => $featureRequests->concat($projectRequests)
            ->sortByDesc('updated_at')->take($validated['limit'])->values()->all()];
    }

    private function search(array $arguments, Workspace $workspace, User $user): array
    {
        $validated = $this->validate($arguments, [
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'limit' => ['required', 'integer', 'between:1,20'],
        ]);
        if (isset($validated['error'])) {
            return $validated;
        }

        $like = '%'.addcslashes($validated['query'], '%_\\').'%';
        $each = max(1, (int) ceil($validated['limit'] / 4));
        $results = collect();

        Project::query()->visibleTo($user)->where('workspace_id', $workspace->id)
            ->where('name', 'like', $like)->latest()->limit($each)->get()
            ->each(fn (Project $project) => $results->push([
                'type' => $project->isSystem() ? 'system' : 'project',
                'title' => $project->name,
                'updated_at' => $project->updated_at->toIso8601String(),
                'url' => $project->isSystem()
                    ? route('app.features.show', [$workspace, $project])
                    : route('app.projects.show', $project),
            ]));
        Task::query()->visibleTo($user)->where('tasks.workspace_id', $workspace->id)
            ->where('title', 'like', $like)->latest()->limit($each)->get()
            ->each(fn (Task $task) => $results->push([
                'type' => 'task', 'title' => $task->title,
                'updated_at' => $task->updated_at->toIso8601String(),
                'url' => route('app.tasks.show', $task),
            ]));
        FeatureRequest::query()->visibleTo($user, $workspace)->where('workspace_id', $workspace->id)
            ->where('title', 'like', $like)->latest()->limit($each)->get()
            ->each(fn (FeatureRequest $request) => $results->push([
                'type' => 'feature_request', 'title' => $request->title,
                'updated_at' => $request->updated_at->toIso8601String(),
                'url' => route('app.approvals.show', [$workspace, $request]),
            ]));
        ProjectRequest::query()->visibleTo($user, $workspace)->where('workspace_id', $workspace->id)
            ->where('title', 'like', $like)->latest()->limit($each)->get()
            ->each(fn (ProjectRequest $request) => $results->push([
                'type' => 'project_request', 'title' => $request->title,
                'updated_at' => $request->updated_at->toIso8601String(),
                'url' => route('app.approvals.projects.show', [$workspace, $request]),
            ]));

        return ['results' => $results->sortByDesc('updated_at')->take($validated['limit'])->values()->all()];
    }

    private function portfolioHealth(array $arguments, Workspace $workspace, User $user): array
    {
        $validated = $this->validate($arguments, [
            'limit' => ['required', 'integer', 'between:1,30'],
        ]);
        if (isset($validated['error'])) {
            return $validated;
        }

        $portfolio = app(PortfolioService::class)->forWorkspace($workspace, $user);

        return [
            'summary' => $portfolio['summary'],
            'projects' => collect($portfolio['projects'])->take($validated['limit'])->map(fn (array $row) => [
                'name' => $row['name'],
                'type' => $row['type'],
                'forecast' => $row['forecast']['state'],
                'progress' => $row['forecast']['progress'],
                'blocked' => $row['forecast']['blocked'],
                'critical' => $row['forecast']['critical'],
                'projected_finish' => $row['forecast']['projected_finish'],
                'reason' => $row['forecast']['reason'],
            ])->values()->all(),
            'latest_insight' => $portfolio['latest_insight'],
        ];
    }

    private function criticalTasks(array $arguments, Workspace $workspace, User $user): array
    {
        $validated = $this->validate($arguments, [
            'project_public_id' => ['required', 'string', 'size:26'],
            'limit' => ['required', 'integer', 'between:1,30'],
        ]);
        if (isset($validated['error'])) {
            return $validated;
        }

        $project = $this->project($validated['project_public_id'], $workspace, $user);
        if (! $project) {
            return ['error' => 'Project not found or not visible to this user.'];
        }

        $path = app(CriticalPathAnalyzer::class)->forProject($project);
        $critical = collect($path['tasks'])
            ->where('is_critical', true)
            ->take($validated['limit'])
            ->values()
            ->all();

        return [
            'project' => $project->name,
            'project_duration_days' => $path['project_duration_days'],
            'projected_finish' => $path['projected_finish']?->toDateString(),
            'critical_tasks' => $critical,
        ];
    }

    private function project(string $publicId, Workspace $workspace, User $user): ?Project
    {
        return Project::query()->visibleTo($user)
            ->where('workspace_id', $workspace->id)
            ->where('public_id', $publicId)
            ->first();
    }

    private function taskData(Task $task): array
    {
        return [
            'title' => $task->title,
            'project' => $task->project->name,
            'status' => $task->status->name,
            'priority' => $task->priority->value,
            'due_at' => $task->due_at?->toIso8601String(),
            'estimate_minutes' => $task->estimate_minutes,
            'url' => route('app.tasks.show', $task),
        ];
    }

    /** @return array<string, mixed> */
    private function validate(array $arguments, array $rules): array
    {
        $validator = Validator::make($arguments, $rules);

        return $validator->fails()
            ? ['error' => 'Invalid tool arguments.', 'details' => $validator->errors()->toArray()]
            : $validator->validated();
    }
}
