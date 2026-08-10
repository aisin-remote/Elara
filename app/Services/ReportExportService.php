<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly PerformanceService $performance,
    ) {}

    public function csv(Workspace $workspace, User $user, array $filters): StreamedResponse
    {
        $period = $this->dashboard->period($workspace, $filters);
        $query = $this->reportTasks($workspace, $user, $filters, $period);
        $filename = "orbitra-report-{$workspace->public_id}-{$period['from']->format('Ymd')}-{$period['to']->format('Ymd')}.csv";

        return response()->streamDownload(function () use ($query, $period): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Task ID', 'Task', 'Project', 'Status', 'Priority', 'Assignees', 'Created', 'Due', 'Completed']);

            $query->chunkById(500, function ($tasks) use ($handle, $period): void {
                foreach ($tasks as $task) {
                    fputcsv($handle, [
                        $task->public_id,
                        $task->title,
                        $task->project->name,
                        $task->status->name,
                        $task->priority->label(),
                        $task->assignees->pluck('name')->join(', '),
                        $task->created_at->timezone($period['timezone'])->format('Y-m-d H:i'),
                        $task->due_at?->timezone($period['timezone'])->format('Y-m-d H:i'),
                        $task->completed_at?->timezone($period['timezone'])->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Workspace $workspace, User $user, array $filters)
    {
        $period = $this->dashboard->period($workspace, $filters);
        $report = $this->performance->forWorkspace($workspace, $user, $filters);
        $tasks = $this->reportTasks($workspace, $user, $filters, $period)->limit(500)->get();
        $filename = "orbitra-report-{$workspace->public_id}-{$period['from']->format('Ymd')}-{$period['to']->format('Ymd')}.pdf";

        return Pdf::loadView('app.performance.report-pdf', compact('workspace', 'report', 'tasks'))
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function reportTasks(Workspace $workspace, User $user, array $filters, array $period): Builder
    {
        return $this->dashboard->activeInPeriod(
            $this->dashboard->taskQuery($workspace, $user, $filters),
            $period['from_utc'],
            $period['to_utc'],
        )->with(['project:id,name', 'status:id,name', 'assignees:id,first_name,last_name'])->orderBy('tasks.id');
    }
}
