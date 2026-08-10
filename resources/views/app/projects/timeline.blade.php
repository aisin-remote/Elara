@extends('layouts.app')

@section('title', $project->name.' Timeline')
@section('page-title', $project->name)

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500">Projects / {{ $project->name }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">Project timeline</h2>
            <p class="mt-1 text-xs text-slate-500">{{ $criticalCount }} critical · projected finish {{ $projectedFinish ?? 'n/a' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $project)
                <form method="POST" action="{{ route('internal.projects.baseline', $project) }}">@csrf<x-button type="submit" variant="secondary">Capture baseline</x-button></form>
                <form method="POST" action="{{ route('internal.projects.reschedule', $project) }}">@csrf<x-button type="submit" variant="secondary">Reschedule from dependencies</x-button></form>
            @endcan
            @can('create', [App\Models\Task::class, $project])<x-button type="button" onclick="document.getElementById('new-task-dialog').showModal()"><x-icon name="plus"/>Add task</x-button>@endcan
        </div>
    </div>

    @include('app.projects._tabs', ['project' => $project])

    <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="milestones-title">
        <div class="flex flex-wrap items-start justify-between gap-4"><div><h3 id="milestones-title" class="text-lg font-bold">Milestones</h3><p class="mt-1 text-xs text-slate-500">Zero-duration targets shown as diamonds on the timeline.</p></div>@can('update',$project)<details class="relative"><summary class="inline-flex min-h-10 cursor-pointer list-none items-center rounded-xl border border-slate-300 px-3 text-sm font-semibold hover:border-orbit-400">+ Add milestone</summary><form method="POST" action="{{ route('internal.project-milestones.store',$project) }}" class="absolute right-0 z-30 mt-2 w-72 space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xl dark:border-slate-700 dark:bg-slate-900">@csrf<div><x-label for="milestone-name">Name</x-label><x-input id="milestone-name" name="name" required maxlength="160" /></div><div><x-label for="milestone-target">Target date</x-label><x-input id="milestone-target" type="date" name="target_date" required /></div><x-button class="w-full">Create milestone</x-button></form></details>@endcan</div>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">@forelse($milestones as $milestone)<article class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"><div class="flex items-start gap-3"><span class="mt-1 size-3 shrink-0 rotate-45 {{ $milestone->completed_at ? 'bg-emerald-500' : 'bg-violet-500' }}"></span><div class="min-w-0 flex-1"><h4 class="truncate font-bold">{{ $milestone->name }}</h4><p class="mt-1 text-xs text-slate-500">{{ $milestone->target_date->format('M j, Y') }} · {{ $milestone->tasks_count }} {{ Str::plural('task', $milestone->tasks_count) }}</p></div><span class="rounded-full px-2 py-1 text-[10px] font-bold {{ $milestone->completed_at ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200' : 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-200' }}">{{ $milestone->completed_at ? 'Complete' : 'Upcoming' }}</span></div>@can('update',$project)<details class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800"><summary class="cursor-pointer text-xs font-bold text-orbit-700">Edit milestone</summary><form method="POST" action="{{ route('internal.project-milestones.update',$milestone) }}" class="mt-3 space-y-3">@csrf @method('PATCH')<x-input name="name" value="{{ $milestone->name }}" required maxlength="160" /><x-input type="date" name="target_date" value="{{ $milestone->target_date->toDateString() }}" required /><label class="flex items-center gap-2 text-sm"><input type="hidden" name="completed" value="0"><input type="checkbox" name="completed" value="1" class="rounded border-slate-300 text-orbit-600" @checked($milestone->completed_at)> Complete</label><x-button variant="secondary" class="w-full">Save milestone</x-button></form><form method="POST" action="{{ route('internal.project-milestones.destroy',$milestone) }}" class="mt-2" onsubmit="return confirm('Remove this milestone? Tasks will remain.')">@csrf @method('DELETE')<x-button variant="danger" class="w-full">Remove milestone</x-button></form></details>@endcan</article>@empty<div class="md:col-span-2 xl:col-span-3"><x-empty-state icon="calendar" title="No milestones yet" description="Add the first project target to anchor the delivery plan." class="p-6" /></div>@endforelse</div>
    </section>

    <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="task-gantt-title">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 id="task-gantt-title" class="text-lg font-bold">Task schedule</h3>
                <p class="mt-1 text-xs text-slate-500">{{ $workspace->timezone }} · bars run from start date to due date</p>
            </div>
            <nav class="inline-flex w-fit rounded-xl bg-slate-100 p-1 text-xs font-semibold dark:bg-slate-800" aria-label="Timeline scale">
                @foreach (App\Support\GanttTimeline::SCALES as $scale => $label)
                    <a href="{{ request()->fullUrlWithQuery(['scale' => $scale]) }}" @if($timeline->scale === $scale) aria-current="page" @endif class="rounded-lg px-3 py-2 transition {{ $timeline->scale === $scale ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' }}">{{ $label }}</a>
                @endforeach
            </nav>
        </div>

        @if ($rows->isNotEmpty() || $milestoneRows->isNotEmpty())
            @php($ticks = $timeline->ticks())
            @php($todayPosition = $timeline->todayPosition())
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Scrollable task timeline">
                <div class="relative grid grid-cols-[minmax(180px,240px)_minmax(0,1fr)]" style="min-width: {{ $timeline->minWidth() }}px">
                    <div class="sticky left-0 z-20 flex items-end border-b border-r border-slate-200 bg-white px-5 py-3 text-[11px] font-bold uppercase tracking-[.14em] text-slate-400 dark:border-slate-800 dark:bg-slate-900">Task</div>
                    <div class="relative h-14 border-b border-slate-200 dark:border-slate-800" aria-hidden="true">
                        @foreach ($ticks as $tick)
                            <span class="absolute inset-y-0 border-l border-slate-200 dark:border-slate-800" style="left: {{ $tick['left'] }}%"><span class="absolute left-1 top-3 whitespace-nowrap text-[10px] font-medium text-slate-400">{{ $tick['label'] }}</span></span>
                        @endforeach
                        @if ($todayPosition !== null)
                            <span class="absolute inset-y-0 z-10 border-l-2 border-slate-900 dark:border-orbit-300" style="left: {{ $todayPosition }}%"><span class="absolute -left-1 top-0 size-2 -translate-x-1/2 rotate-45 bg-slate-900 dark:bg-orbit-300"></span><span class="absolute left-1 top-8 text-[10px] font-bold text-slate-700 dark:text-slate-200">Today</span></span>
                        @endif
                    </div>

                    @if($dependencyLines->isNotEmpty())
                        <svg data-dependency-lines class="pointer-events-none absolute z-[5] overflow-visible" style="left:240px;top:56px;width:calc(100% - 240px);height:calc(100% - 56px)" viewBox="0 0 100 {{ max(1, $chartRowCount * 56) }}" preserveAspectRatio="none" aria-hidden="true">
                            @foreach($dependencyLines as $line)
                                @php($mid = round(($line['from_x'] + $line['to_x']) / 2, 3))
                                <path d="M {{ $line['from_x'] }} {{ $line['from_y'] }} H {{ $mid }} V {{ $line['to_y'] }} H {{ $line['to_x'] }}" fill="none" stroke="{{ ($line['critical'] ?? false) ? '#7c3aed' : ($line['completed'] ? '#10b981' : '#f43f5e') }}" stroke-width="{{ ($line['critical'] ?? false) ? '2' : '1.5' }}" vector-effect="non-scaling-stroke" opacity=".8" />
                            @endforeach
                        </svg>
                    @endif

                    @foreach ($milestoneRows as $milestone)
                        <div class="sticky left-0 z-20 flex min-w-0 items-center gap-3 border-b border-r border-slate-200 bg-white px-5 py-3 dark:border-slate-800 dark:bg-slate-900">
                            <span class="size-3 shrink-0 rotate-45 {{ $milestone['completed'] ? 'bg-emerald-500' : 'bg-violet-500' }}"></span>
                            <span class="min-w-0"><span class="block truncate text-sm font-semibold">{{ $milestone['name'] }}</span><span class="mt-0.5 block truncate text-[10px] text-slate-400">Milestone · {{ $milestone['target_date'] }} · {{ $milestone['task_count'] }} {{ Str::plural('task', $milestone['task_count']) }}</span></span>
                        </div>
                        <div class="relative h-14 border-b border-slate-200 dark:border-slate-800">
                            @foreach ($ticks as $tick)<span class="absolute inset-y-0 border-l border-slate-100 dark:border-slate-800/80" style="left: {{ $tick['left'] }}%" aria-hidden="true"></span>@endforeach
                            @if ($todayPosition !== null)<span class="absolute inset-y-0 z-10 border-l-2 border-slate-900 dark:border-orbit-300" style="left: {{ $todayPosition }}%" aria-hidden="true"></span>@endif
                            <span class="absolute top-1/2 z-10 size-4 -translate-x-1/2 -translate-y-1/2 rotate-45 border-2 border-white shadow {{ $milestone['completed'] ? 'bg-emerald-500' : 'bg-violet-500' }}" style="left:{{ $milestone['left'] }}%" title="{{ $milestone['name'] }} · {{ $milestone['target_date'] }}"></span>
                        </div>
                    @endforeach

                    @foreach ($rows as $row)
                        <a href="{{ route('app.tasks.show', $row['public_id']) }}" class="sticky left-0 z-20 flex min-w-0 items-center gap-3 border-b border-r border-slate-200 bg-white px-5 py-3 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800/70">
                            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $row['color'] }}"></span>
                            <span class="min-w-0"><span class="flex items-center gap-2"><span class="block truncate text-sm font-semibold">{{ $row['title'] }}</span>@if($row['is_critical'] ?? false)<span class="shrink-0 rounded-full bg-violet-100 px-1.5 py-0.5 text-[9px] font-bold text-violet-700">Critical</span>@endif @if($row['is_blocked'])<span class="shrink-0 rounded-full bg-rose-100 px-1.5 py-0.5 text-[9px] font-bold text-rose-700">Blocked</span>@endif</span><span class="mt-0.5 block truncate text-[10px] text-slate-400">{{ $row['status'] }} · {{ $row['date_label'] }}@if(($row['slack'] ?? null) !== null) · slack {{ $row['slack'].'d' }}@endif @if($row['baseline_label'] ?? null) · baseline {{ $row['baseline_label'] }}@endif @if($row['milestone']) · ◆ {{ $row['milestone'] }}@endif @if($row['dependencies']) · waits for {{ collect($row['dependencies'])->pluck('title')->join(', ') }}@endif</span></span>
                        </a>
                        <div class="relative h-14 border-b border-slate-200 dark:border-slate-800">
                            @foreach ($ticks as $tick)<span class="absolute inset-y-0 border-l border-slate-100 dark:border-slate-800/80" style="left: {{ $tick['left'] }}%" aria-hidden="true"></span>@endforeach
                            @if ($todayPosition !== null)<span class="absolute inset-y-0 z-10 border-l-2 border-slate-900 dark:border-orbit-300" style="left: {{ $todayPosition }}%" aria-hidden="true"></span>@endif
                            <a href="{{ route('app.tasks.show', $row['public_id']) }}" class="absolute top-2.5 z-10 flex h-9 min-w-8 items-center overflow-hidden rounded-xl border px-2 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $row['is_overdue'] ? 'ring-2 ring-rose-400' : '' }} {{ $row['is_blocked'] ? 'ring-2 ring-rose-300' : '' }} {{ ($row['is_critical'] ?? false) ? 'ring-2 ring-violet-400' : '' }}" style="left: {{ $row['left'] }}%; width: {{ $row['width'] }}%; border-color: {{ $row['color'] }}; background-color: color-mix(in srgb, {{ $row['color'] }} 13%, white)" aria-label="{{ $row['title'] }}, {{ $row['date_label'] }}, {{ $row['progress'] }} percent complete{{ $row['is_overdue'] ? ', overdue' : '' }}{{ $row['is_blocked'] ? ', blocked' : '' }}{{ ($row['is_critical'] ?? false) ? ', critical path' : '' }}">
                                <span class="absolute inset-y-0 left-0 opacity-20" style="width: {{ $row['progress'] }}%; background-color: {{ $row['color'] }}" aria-hidden="true"></span>
                                <span class="relative flex min-w-0 flex-1 items-center gap-2">
                                    @if ($row['assignees'])
                                        <span class="hidden shrink-0 items-center sm:flex">
                                            @foreach ($row['assignees'] as $assignee)
                                                <x-avatar :src="$assignee['has_avatar'] ? route('internal.users.avatar', $assignee['public_id']) : null" :name="$assignee['name']" size="size-5" class="-ml-1 border-2 border-white first:ml-0 dark:border-slate-900" />
                                            @endforeach
                                        </span>
                                    @endif
                                    <span class="truncate text-[11px] font-semibold text-slate-700 dark:text-slate-950">{{ $row['title'] }}</span>
                                    <span class="ml-auto shrink-0 text-[10px] font-bold text-slate-700 dark:text-slate-950">{{ $row['progress'] }}%</span>
                                </span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
            @if ($hiddenCount > 0 || $hiddenMilestoneCount > 0)
                <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500 dark:border-slate-800">{{ $hiddenCount }} scheduled {{ \Illuminate\Support\Str::plural('task', $hiddenCount) }} and {{ $hiddenMilestoneCount }} {{ \Illuminate\Support\Str::plural('milestone', $hiddenMilestoneCount) }} fall outside this window — widen the scale to see them.</p>
            @endif
        @else
            <div class="p-5"><x-empty-state icon="calendar" title="Nothing scheduled in this window" description="Give tasks a start or due date, or switch to a wider scale, to see them on the timeline." /></div>
        @endif
    </section>

    @if ($unscheduled->isNotEmpty())
        <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="unscheduled-title">
            <h3 id="unscheduled-title" class="text-lg font-bold">Not scheduled</h3>
            <p class="mt-1 text-xs text-slate-500">These tasks have no start or due date, so they never appear on the chart.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($unscheduled as $task)
                    <a href="{{ route('app.tasks.show', $task->public_id) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                        <span class="size-2 rounded-full" style="background-color: {{ $task->status->color ?: '#64748b' }}"></span>{{ $task->title }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @can('create', [App\Models\Task::class, $project])
        @include('app.tasks._create-dialog')
    @endcan
@endsection
