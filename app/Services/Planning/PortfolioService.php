<?php

namespace App\Services\Planning;

use App\Models\DeliveryInsight;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class PortfolioService
{
    public function __construct(private readonly ForecastHealthService $forecast) {}

    /**
     * @return array{
     *     summary: array{projects: int, on_track: int, at_risk: int, late: int, blocked_tasks: int, critical_tasks: int},
     *     projects: array<int, array<string, mixed>>,
     *     latest_insight: ?array<string, mixed>
     * }
     */
    public function forWorkspace(Workspace $workspace, User $user): array
    {
        $projects = $workspace->projects()
            ->visibleTo($user)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();

        $rows = $projects->map(function ($project) {
            $forecast = $this->forecast->forProject($project);

            return [
                'public_id' => $project->public_id,
                'name' => $project->name,
                'type' => $project->type->value,
                'color' => $project->color,
                'status' => $project->status->value,
                'due_date' => $project->due_date?->toDateString(),
                'forecast' => $forecast,
            ];
        });

        $insight = DeliveryInsight::query()
            ->where('workspace_id', $workspace->id)
            ->latest('period_end')
            ->first();

        return [
            'summary' => [
                'projects' => $rows->count(),
                'on_track' => $rows->where('forecast.state', 'on_track')->count()
                    + $rows->where('forecast.state', 'complete')->count(),
                'at_risk' => $rows->where('forecast.state', 'at_risk')->count(),
                'late' => $rows->where('forecast.state', 'late')->count(),
                'blocked_tasks' => (int) $rows->sum(fn (array $row) => $row['forecast']['blocked']),
                'critical_tasks' => (int) $rows->sum(fn (array $row) => $row['forecast']['critical']),
            ],
            'projects' => $rows->values()->all(),
            'latest_insight' => $insight ? [
                'public_id' => $insight->public_id,
                'period_start' => $insight->period_start->toDateString(),
                'period_end' => $insight->period_end->toDateString(),
                'summary' => $insight->summary,
                'source' => $insight->source,
                'generated_at' => $insight->generated_at->toIso8601String(),
            ] : null,
        ];
    }

    /** Compact payload for weekly insight generation / Ask AI. */
    public function snapshot(Workspace $workspace, User $user): Collection
    {
        return collect($this->forWorkspace($workspace, $user)['projects']);
    }
}
