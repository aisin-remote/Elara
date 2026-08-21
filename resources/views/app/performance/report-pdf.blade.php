<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Elara performance report</title>
    <style>
        @page { margin: 28px; }
        body { color: #0f172a; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 20px 0 8px; }
        .muted { color: #64748b; }
        .metrics { width: 100%; margin-top: 18px; }
        .metric { border: 1px solid #e2e8f0; padding: 12px; width: 16.6%; }
        .metric strong { display: block; font-size: 18px; margin-top: 6px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #f1f5f9; color: #475569; font-size: 8px; padding: 7px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #e2e8f0; padding: 7px; vertical-align: top; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Elara performance report</h1>
    <p class="muted">{{ $workspace->name }} · {{ $report['period']['label'] }} · {{ $report['period']['timezone'] }}</p>
    <table class="metrics"><tr>
        <td class="metric">Active projects<strong>{{ $report['summary']['active_projects'] }}</strong></td>
        <td class="metric">In progress<strong>{{ $report['kpis']['in_progress']['value'] }}</strong></td>
        <td class="metric">Overdue<strong>{{ $report['kpis']['overdue']['value'] }}</strong></td>
        <td class="metric">Completed<strong>{{ $report['kpis']['completed']['value'] }}</strong></td>
        <td class="metric">Completion rate<strong>{{ number_format($report['summary']['completion_rate'], 1) }}%</strong></td>
        <td class="metric">Average completion<strong>{{ number_format($report['summary']['average_completion_hours'], 1) }}h</strong></td>
    </tr></table>
    <h2>Filtered tasks</h2>
    <table><thead><tr><th>Task</th><th>Project</th><th>Status</th><th>Priority</th><th>Assignees</th><th>Due</th><th>Completed</th></tr></thead><tbody>
        @forelse($tasks as $task)
            <tr><td>{{ $task->title }}</td><td>{{ $task->project->name }}</td><td>{{ $task->status->name }}</td><td>{{ $task->priority->label() }}</td><td>{{ $task->assignees->pluck('name')->join(', ') ?: '—' }}</td><td>{{ $task->due_at?->timezone($report['period']['timezone'])->format('Y-m-d H:i') ?? '—' }}</td><td>{{ $task->completed_at?->timezone($report['period']['timezone'])->format('Y-m-d H:i') ?? '—' }}</td></tr>
        @empty
            <tr><td colspan="7">No tasks match the active filters.</td></tr>
        @endforelse
    </tbody></table>
    @if($tasks->count() === 500)<p class="muted">The PDF task table is limited to the first 500 rows. Use CSV for the complete filtered dataset.</p>@endif
</body>
</html>
