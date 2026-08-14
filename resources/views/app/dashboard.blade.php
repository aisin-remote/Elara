@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    @php
        $kpiCards = [
            ['total', 'Total tasks', 'All eligible tasks', 'bg-orbit-50 text-orbit-700 dark:bg-orbit-950/50 dark:text-orbit-300'],
            ['in_progress', 'Tasks in progress', 'Active workflow', 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'],
            ['overdue', 'Task overdue', 'Needs attention', 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300'],
            ['completed', 'Completed tasks', 'Finished in range', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'],
        ];
    @endphp

    <section class="flex flex-col gap-5 border-b border-slate-200 pb-6 dark:border-slate-800 lg:flex-row lg:items-end lg:justify-between" aria-labelledby="dashboard-welcome">
        <div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ now($dashboard['period']['timezone'])->format('l, F j') }}</p>
            <h2 id="dashboard-welcome" class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">Welcome back, {{ auth()->user()->first_name }}</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Track what is moving across <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $workspace->name }}</span>.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($canCreateProject)
                <a href="{{ route('app.projects.create', $workspace) }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:border-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"><x-icon name="plus" />New project</a>
            @endif
            @if($creatableProjects->isNotEmpty())<details class="group relative">
                <summary class="inline-flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-orbit-500 dark:text-slate-950"><x-icon name="plus" />Add task</summary>
                <div class="absolute right-0 z-20 mt-2 w-64 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    <p class="px-3 py-2 text-xs font-bold uppercase tracking-[.14em] text-slate-400">Choose project</p>
                    @foreach ($creatableProjects as $project)<a href="{{ route('app.projects.tasks', [$workspace, $project, 'create' => 1]) }}" class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800"><span class="size-2 rounded-full" style="background:{{ $project->color }}"></span><span class="truncate">{{ $project->name }}</span></a>@endforeach
                </div>
            </details>@endif
        </div>
    </section>

    <form method="GET" class="mt-5 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900" aria-label="Dashboard date range">
        <label class="min-w-40 flex-1 sm:flex-none"><span class="mb-1 block text-xs font-semibold text-slate-500">From</span><x-input type="date" name="from" value="{{ $dashboard['period']['from'] }}" /></label>
        <label class="min-w-40 flex-1 sm:flex-none"><span class="mb-1 block text-xs font-semibold text-slate-500">To</span><x-input type="date" name="to" value="{{ $dashboard['period']['to'] }}" /></label>
        <input type="hidden" name="distribution" value="{{ $dashboard['distribution']['type'] }}">
        <input type="hidden" name="gantt_view" value="{{ $dashboard['gantt']['view'] }}">
        <input type="hidden" name="gantt_scale" value="{{ $dashboard['gantt']['scale'] }}">
        <x-button variant="secondary">Update range</x-button>
        <span class="ml-auto hidden text-xs text-slate-400 md:block">{{ $dashboard['period']['timezone'] }}</span>
    </form>

    <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Task key performance indicators">
        @foreach ($kpiCards as [$key, $label, $description, $tone])
            @php($metric = $dashboard['kpis'][$key])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/[.02] dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div><p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $label }}</p><p class="mt-2 text-3xl font-bold tracking-tight">{{ number_format($metric['value']) }}</p></div>
                    <span class="grid size-10 place-items-center rounded-xl {{ $tone }}"><x-icon :name="$key === 'completed' ? 'performance' : ($key === 'overdue' ? 'clock' : 'tasks')" /></span>
                </div>
                <div class="mt-4 flex items-center justify-between gap-2 text-xs">
                    <span class="inline-flex items-center gap-1 font-semibold {{ $metric['delta'] > 0 ? 'text-emerald-600' : ($metric['delta'] < 0 ? 'text-rose-600' : 'text-slate-500') }}">
                        @if($metric['delta'] > 0)<x-icon name="arrow-up" class="size-3" />@elseif($metric['delta'] < 0)<x-icon name="arrow-down" class="size-3" />@endif
                        {{ $metric['delta'] > 0 ? '+' : '' }}{{ $metric['delta'] }}
                    </span>
                    <span class="text-right text-slate-400">{{ $description }} · previous period</span>
                </div>
            </article>
        @endforeach
    </section>

    <div class="mt-8 grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(300px,340px)]">
    @php($timelineView = $dashboard['gantt']['view'])
    @php($timelineItems = $timelineView === 'tasks' ? $dashboard['gantt']['tasks'] : $dashboard['gantt']['projects'])
    <div class="flex min-w-0 flex-col">
        <nav class="relative z-10 flex w-fit items-end" aria-label="Timeline view" role="tablist" data-timeline-tabs>
            @foreach(['projects' => 'Projects', 'tasks' => 'Tasks'] as $view => $label)
                <a href="{{ request()->fullUrlWithQuery(['gantt_view' => $view, 'gantt_scale' => $view === 'tasks' ? 'weekly' : 'monthly']) }}" role="tab" aria-selected="{{ $timelineView === $view ? 'true' : 'false' }}" aria-controls="dashboard-timeline-card" class="-mb-px -mr-px min-w-28 border px-5 py-3 text-center text-sm font-semibold transition first:rounded-tl-xl last:rounded-tr-xl {{ $timelineView === $view ? 'border-slate-200 border-b-white bg-white text-slate-950 dark:border-slate-800 dark:border-b-slate-900 dark:bg-slate-900 dark:text-white' : 'border-slate-200 bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 dark:border-slate-800 dark:bg-slate-800/80 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}">{{ $label }}</a>
            @endforeach
        </nav>
    <section id="dashboard-timeline-card" class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-b-2xl rounded-tr-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="timeline-title" data-gantt-view="{{ $timelineView }}">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h3 id="timeline-title" class="text-lg font-bold">{{ $timelineView === 'tasks' ? 'Task timeline' : 'Project timeline' }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ $timelineView === 'tasks' ? 'Your personal tasks and assigned work across the selected timeline' : 'Visible project dates and task completion progress' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ $timelineView === 'tasks' ? route('app.tasks.index', $workspace) : route('app.projects.index', $workspace) }}" class="text-xs font-bold text-orbit-700 hover:text-orbit-800 dark:text-orbit-300 dark:hover:text-orbit-200">View all</a>
                <nav class="inline-flex w-fit shrink-0 rounded-xl bg-slate-100 p-1 text-xs font-semibold dark:bg-slate-800" aria-label="Timeline scale">
                    @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $scale => $label)
                        <a href="{{ request()->fullUrlWithQuery(['gantt_scale' => $scale]) }}" @if($dashboard['gantt']['scale'] === $scale) aria-current="page" @endif class="rounded-lg px-3 py-2 transition {{ $dashboard['gantt']['scale'] === $scale ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
        </div>

        @if($timelineItems)
            <div class="min-h-0 flex-1 overflow-x-auto" tabindex="0" role="region" aria-label="Scrollable {{ $timelineView === 'tasks' ? 'task' : 'project' }} Gantt chart">
                <div class="grid min-h-full grid-cols-[minmax(170px,220px)_minmax(0,1fr)]" style="min-width: {{ $dashboard['gantt']['min_width'] }}px; grid-template-rows: 3.5rem repeat({{ count($timelineItems) }}, minmax(4rem, 1fr))" data-gantt-chart>
                    <div class="sticky left-0 z-20 flex items-end border-b border-r border-slate-200 bg-white px-5 py-3 text-[11px] font-bold uppercase tracking-[.14em] text-slate-400 dark:border-slate-800 dark:bg-slate-900">{{ $timelineView === 'tasks' ? 'Task' : 'Project' }}</div>
                    <div class="relative h-14 border-b border-slate-200 dark:border-slate-800" aria-hidden="true">
                        @foreach($dashboard['gantt']['ticks'] as $tick)
                            <span class="absolute inset-y-0 border-l border-slate-200 dark:border-slate-800" style="left: {{ $tick['left'] }}%"><span class="absolute left-1 top-3 whitespace-nowrap text-[10px] font-medium text-slate-400">{{ $tick['label'] }}</span></span>
                        @endforeach
                        @if($dashboard['gantt']['today_position'] !== null)
                            <span class="absolute inset-y-0 z-10 border-l-2 border-slate-900 dark:border-orbit-300" style="left: {{ $dashboard['gantt']['today_position'] }}%"><span class="absolute -left-1 top-0 size-2 -translate-x-1/2 rotate-45 bg-slate-900 dark:bg-orbit-300"></span><span class="absolute left-1 top-8 text-[10px] font-bold text-slate-700 dark:text-slate-200">Today</span></span>
                        @endif
                    </div>

                    @foreach($timelineItems as $item)
                        <a href="{{ $item['url'] }}" class="sticky left-0 z-20 flex min-w-0 items-center gap-3 border-b border-r border-slate-200 bg-white px-5 py-3 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800/70">
                            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold">{{ $item['name'] }}</span>
                                <span class="mt-0.5 block truncate text-[10px] text-slate-400">
                                    @if($timelineView === 'tasks')
                                        {{ $item['context'] }} ·
                                    @endif
                                    {{ $item['date_label'] }}
                                </span>
                            </span>
                        </a>
                        <div class="relative min-h-16 border-b border-slate-200 dark:border-slate-800">
                            @foreach($dashboard['gantt']['ticks'] as $tick)<span class="absolute inset-y-0 border-l border-slate-100 dark:border-slate-800/80" style="left: {{ $tick['left'] }}%" aria-hidden="true"></span>@endforeach
                            @if($dashboard['gantt']['today_position'] !== null)<span class="absolute inset-y-0 z-10 border-l-2 border-slate-900 dark:border-orbit-300" style="left: {{ $dashboard['gantt']['today_position'] }}%" aria-hidden="true"></span>@endif
                            <a href="{{ $item['url'] }}" class="absolute top-1/2 z-10 flex h-10 min-w-8 -translate-y-1/2 items-center overflow-hidden rounded-xl border px-2 shadow-sm transition hover:-translate-y-[calc(50%+0.125rem)] hover:shadow-md" style="left: {{ $item['left'] }}%; width: {{ $item['width'] }}%; border-color: {{ $item['color'] }}; background-color: color-mix(in srgb, {{ $item['color'] }} 13%, white)" aria-label="{{ $item['name'] }}, {{ $item['date_label'] }}, {{ $item['progress'] }} percent complete">
                                <span class="absolute inset-y-0 left-0 opacity-20" style="width: {{ $item['progress'] }}%; background-color: {{ $item['color'] }}" aria-hidden="true"></span>
                                <span class="relative flex min-w-0 flex-1 items-center gap-2">
                                    @if($item['members'])<span class="hidden shrink-0 items-center sm:flex">@foreach($item['members'] as $member)<x-avatar :src="$member['has_avatar'] ? route('internal.users.avatar', $member['public_id']) : null" :name="$member['name']" size="size-6" class="-ml-1 border-2 border-white first:ml-0 dark:border-slate-900" />@endforeach</span>@endif
                                    <span class="truncate text-[11px] font-semibold text-slate-700 dark:text-slate-950">{{ $item['name'] }}</span>
                                    <span class="ml-auto shrink-0 text-[10px] font-bold text-slate-700 dark:text-slate-950">{{ $item['progress'] }}%</span>
                                </span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="flex flex-1 items-center justify-center p-5"><x-empty-state :title="$timelineView === 'tasks' ? 'No personal or assigned tasks in this window' : 'No dated projects in this window'" :description="$timelineView === 'tasks' ? 'Create a personal task, get assigned work, or choose another timeline scale.' : 'Add start and due dates to an accessible project, or choose another timeline scale.'" /></div>
        @endif
    </section>
    </div>

    <section class="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="today-tasks-title">
        @php($overdueCount = collect($dashboard['today_tasks'])->where('is_overdue')->count())
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 p-5 dark:border-slate-800">
            <div class="min-w-0">
                <h3 id="today-tasks-title" class="text-lg font-bold">My focus</h3>
                {{-- Keep whitespace before @if: Blade skips a directive glued to a word character. --}}
                <p class="mt-1 text-xs text-slate-500">
                    Assigned to you, due today
                    @if ($overdueCount)
                        · <span class="font-semibold text-rose-600 dark:text-rose-400">{{ $overdueCount }} still overdue</span>
                    @endif
                </p>
            </div>
            <a href="{{ route('app.tasks.index', $workspace) }}" class="shrink-0 text-xs font-bold text-orbit-700 hover:text-orbit-800 dark:text-orbit-300 dark:hover:text-orbit-200">View all</a>
        </div>
        <div class="grid flex-1 gap-4 overflow-y-auto p-5 sm:grid-cols-2 xl:grid-cols-1 xl:content-start">
            @forelse($dashboard['today_tasks'] as $task)
                <a href="{{ route('app.tasks.show', $task['public_id']) }}" class="group block h-fit rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-950/40 dark:hover:border-slate-700">
                    <div class="flex items-start gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-500 dark:bg-slate-800 dark:text-slate-400"><x-icon name="tasks" class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <h4 class="truncate font-bold text-slate-900 dark:text-white">{{ $task['title'] }}</h4>
                            <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{{ $task['description'] }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $task['priority_color'] }}">{{ $task['priority_label'] }}</span>
                    </div>
                    @if ($task['is_overdue'])
                        <p class="mt-3 flex items-center gap-1.5 text-xs font-bold text-rose-600 dark:text-rose-400"><x-icon name="clock" class="size-3.5" />Overdue since {{ $task['due_label'] }}</p>
                    @endif
                    <div class="mt-4 flex items-center gap-3">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="h-full rounded-full bg-orbit-500" style="width: {{ $task['progress'] }}%"></div>
                        </div>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $task['progress'] }}%</span>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <div class="flex -space-x-1.5">
                            @foreach($task['assignees'] as $assignee)
                                <x-avatar :src="$assignee['has_avatar'] ? route('internal.users.avatar', $assignee['public_id']) : null" :name="$assignee['name']" size="size-6" class="border-2 border-white dark:border-slate-900" />
                            @endforeach
                        </div>
                        <div class="flex items-center gap-3 text-xs font-semibold text-slate-500 dark:text-slate-400">
                            <span class="flex items-center gap-1"><x-icon name="chat" class="size-4" /> {{ $task['comments_count'] }}</span>
                            <span class="flex items-center gap-1"><x-icon name="link" class="size-4" /> {{ $task['files_count'] }}</span>
                        </div>
                    </div>
                </a>
            @empty
                <x-empty-state icon="tasks" title="Nothing due today" description="Tasks assigned to you that are due today, or still open past their date, show up here." class="col-span-full p-8" />
            @endforelse
        </div>
    </section>
    </div>

    <div class="mt-5 grid min-w-0 grid-cols-[minmax(0,1fr)] gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(300px,.65fr)]">
        <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm shadow-slate-950/[.02] dark:border-slate-800 dark:bg-slate-900 sm:p-4" aria-labelledby="task-performance-title">
            <h3 id="task-performance-title" class="px-1 pb-4 text-lg font-bold">Task Performance</h3>
            <div class="rounded-2xl border border-slate-200 p-4 shadow-sm shadow-slate-950/[.03] dark:border-slate-700 sm:p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap gap-x-5 gap-y-2 text-xs font-medium text-slate-500 dark:text-slate-300" aria-label="Task performance legend">
                        <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-emerald-500"></span>Complete</span>
                        <span class="flex items-center gap-1.5"><span class="size-2 rounded-full border border-slate-300 bg-slate-50 dark:border-slate-500 dark:bg-slate-700"></span>New Task</span>
                        <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-rose-500"></span>Overdue</span>
                    </div>
                    <span class="inline-flex min-h-10 w-fit shrink-0 items-center gap-2 rounded-xl border border-slate-200 px-3 text-xs font-medium text-slate-500 dark:border-slate-700 dark:text-slate-300">
                        <x-icon name="calendar" class="size-4" />{{ $dashboard['period']['label'] }}
                    </span>
                </div>
                <div class="mt-4 h-64 sm:h-72"><canvas data-orbitra-chart="performance" data-chart-source="dashboard-trend" aria-label="Task performance chart for {{ $dashboard['period']['label'] }}" role="img">Task performance chart</canvas></div>
            </div>
            <script id="dashboard-trend" type="application/json">@json($dashboard['trend'])</script>
        </section>

        <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="member-task-title">
            <div class="flex items-center justify-between gap-3"><div><h3 id="member-task-title" class="text-lg font-bold">Member task workload</h3><p class="mt-1 text-xs text-slate-500">Open vs completed tasks by member</p></div><span class="shrink-0 rounded-full bg-orbit-50 px-3 py-1 text-xs font-bold text-orbit-700 dark:bg-orbit-950 dark:text-orbit-300">{{ $dashboard['member_task_heatmap']['total_tasks'] }} tasks</span></div>
            @if(count($dashboard['member_task_heatmap']['members']) > 0)
                <div class="mt-5 h-64"><canvas data-orbitra-chart="workload" data-chart-source="dashboard-member-task" aria-label="Member task workload chart" role="img">Member task workload chart</canvas></div>
                <script id="dashboard-member-task" type="application/json">@json($dashboard['member_task_heatmap']['members'])</script>
            @else
                <p class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800">No active members in this workspace.</p>
            @endif
        </section>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="recent-activity-title">
            <div class="flex items-center justify-between gap-3">
                <h3 id="recent-activity-title" class="text-lg font-bold">Recent activity</h3>
                <a href="{{ route('app.workspaces.team', $workspace) }}" class="text-xs font-bold text-orbit-700 dark:text-orbit-300">Open team</a>
            </div>
            <ol class="mt-5 space-y-1">
                @forelse($dashboard['recent_activity'] as $activity)
                    <li class="relative flex gap-3 rounded-xl p-2 transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                        <x-avatar :src="$activity['actor_has_avatar'] ? route('internal.users.avatar', $activity['actor_public_id']) : null" :name="$activity['actor']" size="size-9" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm"><span class="font-semibold">{{ $activity['actor'] }}</span> <span class="text-slate-600 dark:text-slate-300">{{ $activity['action'] }}</span></p>
                            @if ($activity['subject'])
                                <p class="truncate text-sm font-medium text-orbit-700 dark:text-orbit-300">{{ $activity['subject']['label'] }}</p>
                            @endif
                            <time datetime="{{ $activity['occurred_at'] }}" class="mt-1 block text-xs text-slate-400">{{ $activity['relative'] }}</time>
                        </div>
                        @if ($activity['subject'])
                            {{-- Stretched link so the whole row is clickable without nesting interactive elements. --}}
                            <a href="{{ $activity['subject']['url'] }}" class="absolute inset-0 rounded-xl"><span class="sr-only">Open {{ $activity['subject']['label'] }}</span></a>
                        @endif
                    </li>
                @empty
                    <li><x-empty-state icon="clock" title="Nothing has happened yet" description="Activity from projects, tasks, and schedule events you can see will appear here." class="p-8" /></li>
                @endforelse
            </ol>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="members-title">
            <div class="flex items-center justify-between gap-3">
                <div><h3 id="members-title" class="text-lg font-bold">Members</h3><p class="mt-1 text-xs text-slate-500">People with access to {{ $workspace->name }}</p></div>
                <a href="{{ route('app.workspaces.team', $workspace) }}" class="shrink-0 text-xs font-bold text-orbit-700 dark:text-orbit-300">Open team</a>
            </div>
            <ul class="mt-5 max-h-[220px] space-y-1 overflow-y-auto pr-1">
                @forelse($dashboard['members'] as $member)
                    <li>
                        <a href="{{ $member['url'] }}" class="flex items-center gap-3 rounded-xl p-2 transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <x-avatar :src="$member['has_avatar'] ? route('internal.users.avatar', $member['public_id']) : null" :name="$member['name']" size="size-9" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold">{{ $member['name'] }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ $member['job_title'] ?: 'No job title yet' }}</span>
                            </span>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $member['role'] }}</span>
                        </a>
                    </li>
                @empty
                    <li><x-empty-state icon="team" title="No active members" description="Invite teammates from the Team page to see them here." class="p-8" /></li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 lg:col-span-2 dark:border-slate-800 dark:bg-slate-900 xl:col-span-1" aria-labelledby="distribution-title">
            <div class="flex items-center justify-between gap-3"><h3 id="distribution-title" class="text-lg font-bold">Task distribution</h3><div class="flex rounded-lg bg-slate-100 p-1 text-xs font-semibold dark:bg-slate-800"><a href="{{ request()->fullUrlWithQuery(['distribution' => 'status']) }}" class="rounded-md px-2.5 py-1.5 {{ $dashboard['distribution']['type'] === 'status' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500' }}">Status</a><a href="{{ request()->fullUrlWithQuery(['distribution' => 'priority']) }}" class="rounded-md px-2.5 py-1.5 {{ $dashboard['distribution']['type'] === 'priority' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500' }}">Priority</a></div></div>
            @if ($dashboard['distribution']['total'] > 0)
                <p class="mt-4 text-sm text-slate-500">
                    <span class="text-3xl font-bold tracking-tight text-slate-950 dark:text-white">{{ number_format($dashboard['distribution']['total']) }}</span>
                    tasks · mostly <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $dashboard['distribution']['rows'][0]['label'] }}</span>
                </p>
                <ul class="mt-5 space-y-3">
                    @foreach ($dashboard['distribution']['rows'] as $row)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $row['color'] }}"></span>
                                    <span class="truncate font-medium">{{ $row['label'] }}</span>
                                </span>
                                <span class="shrink-0 tabular-nums"><span class="font-bold">{{ $row['value'] }}</span> <span class="text-xs text-slate-400">{{ $row['share'] }}%</span></span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full" style="width: {{ $row['share'] }}%; background-color: {{ $row['color'] }}"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty-state icon="tasks" title="No tasks in this range" description="Widen the date range, or create a task, to see how work is spread across statuses." class="mt-4 p-8" />
            @endif
        </section>
    </div>
@endsection
