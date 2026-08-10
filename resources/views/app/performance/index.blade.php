@extends('layouts.app')

@section('title', 'Performance')
@section('page-title', 'Performance')

@section('content')
    @php
        $exportQuery = request()->query();
        $performanceKpis = [
            ['Active projects', $report['summary']['active_projects'], 'projects', 'text-violet-600 bg-violet-50 dark:bg-violet-950/50 dark:text-violet-300'],
            ['In progress', $report['kpis']['in_progress']['value'], 'tasks', 'text-amber-600 bg-amber-50 dark:bg-amber-950/50 dark:text-amber-300'],
            ['Overdue', $report['kpis']['overdue']['value'], 'clock', 'text-rose-600 bg-rose-50 dark:bg-rose-950/50 dark:text-rose-300'],
            ['Completed', $report['kpis']['completed']['value'], 'performance', 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/50 dark:text-emerald-300'],
        ];
    @endphp

    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><p class="text-sm text-slate-500">Server-calculated insights for {{ $workspace->name }}</p><h2 class="mt-1 text-2xl font-bold tracking-tight">Performance overview</h2><p class="mt-2 text-sm text-slate-500">{{ $report['period']['label'] }} · {{ $report['period']['timezone'] }}</p></div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('internal.reports.csv', ['workspace' => $workspace, ...$exportQuery]) }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold hover:border-slate-400 dark:border-slate-700 dark:bg-slate-900"><x-icon name="download" />CSV</a>
            <a href="{{ route('internal.reports.pdf', ['workspace' => $workspace, ...$exportQuery]) }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-orbit-500 dark:text-slate-950"><x-icon name="download" />PDF report</a>
        </div>
    </div>

    <form method="GET" class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 dark:border-slate-800 dark:bg-slate-900" aria-label="Performance filters">
        <label><span class="mb-1 block text-xs font-semibold text-slate-500">From</span><x-input type="date" name="from" value="{{ $report['period']['from'] }}" /></label>
        <label><span class="mb-1 block text-xs font-semibold text-slate-500">To</span><x-input type="date" name="to" value="{{ $report['period']['to'] }}" /></label>
        <label><span class="mb-1 block text-xs font-semibold text-slate-500">Project</span><x-select name="project_public_id"><option value="">All accessible</option>@foreach($projects->groupBy(fn($project) => $project->type->label()) as $group => $groupProjects)<optgroup label="{{ \Illuminate\Support\Str::plural($group) }}">@foreach($groupProjects as $project)<option value="{{ $project->public_id }}" @selected(($filters['project_public_id'] ?? '') === $project->public_id)>{{ $project->name }}</option>@endforeach</optgroup>@endforeach</x-select></label>
        <label><span class="mb-1 block text-xs font-semibold text-slate-500">Member</span><x-select name="member_public_id"><option value="">All members</option>@foreach($members as $member)<option value="{{ $member->user->public_id }}" @selected(($filters['member_public_id'] ?? '') === $member->user->public_id)>{{ $member->user->name }}</option>@endforeach</x-select></label>
        <label><span class="mb-1 block text-xs font-semibold text-slate-500">Status</span><x-select name="status_public_id"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status->public_id }}" @selected(($filters['status_public_id'] ?? '') === $status->public_id)>{{ $status->name }} · {{ $status->project->name }}</option>@endforeach</x-select></label>
        <label><span class="mb-1 block text-xs font-semibold text-slate-500">Distribution</span><x-select name="distribution"><option value="status" @selected(($filters['distribution'] ?? 'status') === 'status')>Status</option><option value="priority" @selected(($filters['distribution'] ?? '') === 'priority')>Priority</option></x-select></label>
        <div class="flex items-end"><x-button variant="secondary" class="w-full">Apply filters</x-button></div>
    </form>

    <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Performance key metrics">
        @foreach($performanceKpis as [$label, $value, $icon, $tone])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><div class="flex items-center justify-between"><p class="text-sm font-semibold text-slate-500">{{ $label }}</p><span class="grid size-9 place-items-center rounded-xl {{ $tone }}"><x-icon :name="$icon" /></span></div><p class="mt-3 text-3xl font-bold">{{ number_format($value) }}</p></article>
        @endforeach
    </section>

    <section class="mt-5 grid gap-4 md:grid-cols-3" aria-label="Rate insights">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm font-semibold text-slate-500">Average completion time</p><p class="mt-2 text-2xl font-bold">{{ number_format($report['summary']['average_completion_hours'], 1) }} <span class="text-sm font-semibold text-slate-400">hours</span></p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><div class="flex items-center justify-between"><p class="text-sm font-semibold text-slate-500">Completion rate</p><span class="text-sm font-bold text-emerald-600">{{ number_format($report['summary']['completion_rate'], 1) }}%</span></div><div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-emerald-500" style="width:{{ min(100, $report['summary']['completion_rate']) }}%"></div></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><div class="flex items-center justify-between"><p class="text-sm font-semibold text-slate-500">Overdue rate</p><span class="text-sm font-bold text-rose-600">{{ number_format($report['summary']['overdue_rate'], 1) }}%</span></div><div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-rose-500" style="width:{{ min(100, $report['summary']['overdue_rate']) }}%"></div></div></article>
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="completion-trend-title"><h3 id="completion-trend-title" class="text-lg font-bold">Task completion trend</h3><p class="mt-1 text-xs text-slate-500">Completed tasks over the active reporting range</p><div class="mt-5 h-72"><canvas data-orbitra-chart="completion" data-chart-source="performance-trend" aria-label="Task completion trend chart" role="img">Task completion trend</canvas></div></section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="total-completed-title"><h3 id="total-completed-title" class="text-lg font-bold">Created vs completed</h3><p class="mt-1 text-xs text-slate-500">Delivery volume by {{ $report['trend']['unit'] }}</p><div class="mt-5 h-72"><canvas data-orbitra-chart="total-completed" data-chart-source="performance-trend" aria-label="Created versus completed task chart" role="img">Created versus completed tasks</canvas></div></section>
        <script id="performance-trend" type="application/json">@json($report['trend'])</script>
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(280px,.7fr)_minmax(0,1.3fr)]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="performance-distribution-title"><h3 id="performance-distribution-title" class="text-lg font-bold">Task distribution</h3><p class="mt-1 text-xs text-slate-500">By {{ $report['distribution']['type'] }}</p><div class="mt-4 h-64"><canvas data-orbitra-chart="distribution" data-chart-source="performance-distribution" aria-label="Task distribution chart" role="img">Task distribution</canvas></div><script id="performance-distribution" type="application/json">@json($report['distribution'])</script></section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="workload-title"><h3 id="workload-title" class="text-lg font-bold">Team workload</h3><p class="mt-1 text-xs text-slate-500">Open workload and completions per active member</p><div class="mt-4 h-64"><canvas data-orbitra-chart="workload" data-chart-source="performance-workload" aria-label="Team workload chart" role="img">Team workload</canvas></div><script id="performance-workload" type="application/json">@json($report['workload'])</script></section>
    </div>

    <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="bottleneck-title">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800"><div><h3 id="bottleneck-title" class="text-lg font-bold">Bottleneck warning</h3><p class="mt-1 text-xs text-slate-500">Tasks remaining in the same in-progress status for at least {{ $report['bottleneck_threshold_days'] }} days</p></div><span class="rounded-full {{ count($report['bottlenecks']) ? 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' }} px-3 py-1 text-xs font-bold">{{ count($report['bottlenecks']) }} flagged</span></header>
        @if(count($report['bottlenecks']))
            <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-400 dark:bg-slate-900"><tr><th class="px-5 py-3">Task</th><th class="px-5 py-3">Project</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Assignee</th><th class="px-5 py-3 text-right">Age</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">@foreach($report['bottlenecks'] as $task)<tr><td class="px-5 py-4"><a href="{{ route('app.tasks.show', $task['public_id']) }}" class="font-semibold hover:text-orbit-700">{{ $task['title'] }}</a></td><td class="px-5 py-4"><span class="inline-flex items-center gap-2"><span class="size-2 rounded-full" style="background:{{ $task['project_color'] }}"></span>{{ $task['project'] }}</span></td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" style="color:{{ $task['status_color'] }};background:color-mix(in srgb, {{ $task['status_color'] }} 12%, transparent)">{{ $task['status'] }}</span></td><td class="px-5 py-4 text-slate-500">{{ implode(', ', $task['assignees']) ?: 'Unassigned' }}</td><td class="px-5 py-4 text-right font-bold text-rose-600">{{ $task['age_days'] }} days</td></tr>@endforeach</tbody></table></div>
        @else
            <div class="p-8 text-center"><p class="font-semibold">No bottlenecks in this filter</p><p class="mt-1 text-sm text-slate-500">Active tasks are moving within the configured threshold.</p></div>
        @endif
    </section>
@endsection
