<?php

namespace App\Services\Planning;

use App\Enums\ForecastState;
use App\Models\Project;
use Carbon\CarbonImmutable;

class ForecastHealthService
{
    public function __construct(private readonly CriticalPathAnalyzer $criticalPath) {}

    /**
     * @return array{
     *     state: string,
     *     label: string,
     *     progress: int,
     *     blocked: int,
     *     critical: int,
     *     overdue: int,
     *     projected_finish: ?string,
     *     days_left: ?int,
     *     reason: string,
     *     critical_public_ids: array<int, string>,
     *     project_duration_days: int
     * }
     */
    public function forProject(Project $project): array
    {
        $progress = $project->taskProgress();
        $path = $this->criticalPath->forProject($project);
        $timezone = $project->workspace->timezone ?: config('app.timezone');
        $projected = $path['projected_finish'];
        $due = $project->due_date
            ? CarbonImmutable::parse($project->due_date, $timezone)->endOfDay()
            : null;

        $blocked = $project->tasks()->whereNull('archived_at')->blocked()->count();
        $overdue = $progress['overdue'];
        $critical = count($path['critical_public_ids']);
        $daysLeft = $due ? (int) ceil(now($timezone)->diffInSeconds($due, false) / 86400) : null;

        $state = match (true) {
            $progress['percentage'] >= 100 => ForecastState::COMPLETE,
            $overdue > 0 || ($due && $projected && $projected->gt($due)) || ($daysLeft !== null && $daysLeft < 0) => ForecastState::LATE,
            $blocked > 0
                || ($due && $projected && $projected->diffInDays($due, false) <= 2 && $progress['percentage'] < 90)
                || (($project->scheduleHealth($progress['percentage'])['state'] ?? null) === 'behind') => ForecastState::AT_RISK,
            default => ForecastState::ON_TRACK,
        };

        $reason = match ($state) {
            ForecastState::COMPLETE => 'All active work is complete.',
            ForecastState::LATE => $overdue > 0
                ? $overdue.' overdue task'.($overdue === 1 ? '' : 's').'.'
                : 'Projected finish is past the project due date.',
            ForecastState::AT_RISK => $blocked > 0
                ? $blocked.' blocked task'.($blocked === 1 ? '' : 's').' on the board.'
                : 'Schedule progress or critical path is tight against the due date.',
            ForecastState::ON_TRACK => 'Progress and critical path are inside the plan.',
        };

        return [
            'state' => $state->value,
            'label' => $state->label(),
            'progress' => $progress['percentage'],
            'blocked' => $blocked,
            'critical' => $critical,
            'overdue' => $overdue,
            'projected_finish' => $projected?->toDateString(),
            'days_left' => $daysLeft,
            'reason' => $reason,
            'critical_public_ids' => $path['critical_public_ids'],
            'project_duration_days' => $path['project_duration_days'],
        ];
    }
}
