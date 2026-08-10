<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatusCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class PerformanceService
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function forWorkspace(Workspace $workspace, User $user, array $filters = []): array
    {
        $analytics = $this->dashboard->forWorkspace($workspace, $user, $filters);
        $period = $this->dashboard->period($workspace, $filters);
        $base = $this->dashboard->taskQuery($workspace, $user, $filters);
        $eligible = $this->dashboard->activeInPeriod(clone $base, $period['from_utc'], $period['to_utc'])
            ->whereHas('status', fn (Builder $status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value));
        $eligibleTotal = (clone $eligible)->count();
        $completed = $analytics['kpis']['completed']['value'];
        $overdue = $analytics['kpis']['overdue']['value'];

        return [
            ...$analytics,
            'summary' => [
                'active_projects' => Project::query()->visibleTo($user)->where('workspace_id', $workspace->id)
                    ->where('status', ProjectStatus::ACTIVE->value)
                    ->when($filters['project_public_id'] ?? null, fn (Builder $query, string $publicId) => $query->where('public_id', $publicId))
                    ->count(),
                'average_completion_hours' => $this->averageCompletionHours(clone $base, $period),
                'completion_rate' => $eligibleTotal ? round($completed / $eligibleTotal * 100, 1) : 0.0,
                'overdue_rate' => $eligibleTotal ? round($overdue / $eligibleTotal * 100, 1) : 0.0,
            ],
            'workload' => $this->workload($workspace, $user, $filters, $period),
            'bottlenecks' => $this->bottlenecks($workspace, $user, $filters, $period),
            'bottleneck_threshold_days' => (int) config('orbitra.bottleneck_days'),
        ];
    }

    private function averageCompletionHours(Builder $query, array $period): float
    {
        $durations = $query->whereBetween('completed_at', [$period['from_utc'], $period['to_utc']])
            ->get(['created_at', 'completed_at'])
            ->map(fn (Task $task) => $task->created_at->diffInSeconds($task->completed_at));

        return $durations->isEmpty() ? 0.0 : round($durations->average() / 3600, 1);
    }

    private function workload(Workspace $workspace, User $user, array $filters, array $period): array
    {
        $memberships = $workspace->memberships()->active()->with('user:id,public_id,first_name,last_name')
            ->when($filters['member_public_id'] ?? null, fn (Builder $query, string $publicId) => $query
                ->whereHas('user', fn (Builder $member) => $member->where('public_id', $publicId)))
            ->get();
        $memberIds = $memberships->pluck('user_id')->all();
        $result = $memberships->mapWithKeys(fn ($membership) => [$membership->user_id => [
            'public_id' => $membership->user->public_id,
            'name' => $membership->user->name,
            'open' => 0,
            'completed' => 0,
        ]])->all();
        $tasks = $this->dashboard->activeInPeriod($this->dashboard->taskQuery($workspace, $user, $filters), $period['from_utc'], $period['to_utc'])
            ->with(['status:id,category', 'assignees:id'])
            ->get(['id', 'status_id', 'completed_at']);

        foreach ($tasks as $task) {
            $isOpen = ! in_array($task->status->category, [TaskStatusCategory::COMPLETED, TaskStatusCategory::CANCELLED], true);
            $isCompletedInPeriod = $task->completed_at?->betweenIncluded($period['from_utc'], $period['to_utc']) ?? false;
            foreach ($task->assignees->whereIn('id', $memberIds) as $assignee) {
                $result[$assignee->id]['open'] += (int) $isOpen;
                $result[$assignee->id]['completed'] += (int) $isCompletedInPeriod;
            }
        }

        return collect($result)->sortByDesc('open')->values()->all();
    }

    private function bottlenecks(Workspace $workspace, User $user, array $filters, array $period): array
    {
        $threshold = max(1, (int) config('orbitra.bottleneck_days', 7));
        $cutoff = CarbonImmutable::now('UTC')->subDays($threshold);

        return $this->dashboard->activeInPeriod($this->dashboard->taskQuery($workspace, $user, $filters), $period['from_utc'], $period['to_utc'])
            ->where('status_changed_at', '<=', $cutoff)
            ->whereHas('status', fn (Builder $status) => $status->where('category', TaskStatusCategory::IN_PROGRESS->value))
            ->with(['project:id,public_id,name,color', 'status:id,name,color', 'assignees:id,public_id,first_name,last_name'])
            ->oldest('status_changed_at')->limit(10)->get()
            ->map(fn (Task $task) => [
                'public_id' => $task->public_id,
                'title' => $task->title,
                'project' => $task->project->name,
                'project_color' => $task->project->color,
                'status' => $task->status->name,
                'status_color' => $task->status->color,
                'assignees' => $task->assignees->pluck('name')->all(),
                'age_days' => $task->status_changed_at->diffInDays(now()),
            ])->all();
    }
}
