@extends('layouts.requester')

@section('title', 'Timeline')
@section('page-title', 'Timeline')

@section('content')
    @php($ticks = $timeline->ticks())
    @php($todayPosition = $timeline->todayPosition())
    @php($showingFeatures = $view === 'features')
    @php($itemLabel = $showingFeatures ? 'Feature' : 'Project')
    @php($itemLabelLower = strtolower($itemLabel))

    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="text-sm font-semibold text-orbit-700 dark:text-orbit-300">{{ $deliveryWorkspace->name }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">Yang sedang dikerjakan tim IT</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                Ringkasan {{ $itemLabelLower }} department Anda. Klik {{ $itemLabelLower }} untuk melihat jadwal task-nya tanpa membuka detail internal seperti description, comment, file, dan dependency.
            </p>
        </div>
        <nav class="inline-flex w-fit rounded-xl bg-slate-100 p-1 text-xs font-semibold dark:bg-slate-800" aria-label="Timeline scale">
            @foreach (App\Support\GanttTimeline::SCALES as $scale => $label)
                <a href="{{ request()->fullUrlWithQuery(['scale' => $scale]) }}" @if($timeline->scale === $scale) aria-current="page" @endif
                    class="rounded-lg px-3 py-2 transition {{ $timeline->scale === $scale ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['Current '.strtolower($itemLabel).'s', $itemCount, $showingFeatures ? 'sparkles' : 'projects'],
            ['Scheduled tasks', $scheduledTaskCount, 'tasks'],
            ['Last refreshed', $updatedAt->format('H:i'), 'refresh'],
        ] as [$label, $value, $icon])
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-3">
                    <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</p><p class="mt-2 text-2xl font-bold tabular-nums">{{ $value }}</p></div>
                    <span class="grid size-10 place-items-center rounded-xl bg-orbit-50 text-orbit-700 dark:bg-orbit-950 dark:text-orbit-300"><x-icon :name="$icon" /></span>
                </div>
            </div>
        @endforeach
    </div>

    <nav class="relative z-10 mt-6 flex w-fit items-end" aria-label="Timeline view" role="tablist" data-timeline-tabs>
        @foreach (['projects' => 'Projects', 'features' => 'Features'] as $timelineView => $label)
            <a href="{{ request()->fullUrlWithQuery(['view' => $timelineView]) }}" role="tab" aria-selected="{{ $view === $timelineView ? 'true' : 'false' }}" aria-controls="requester-timeline-card"
                class="-mb-px -mr-px min-w-28 border px-5 py-3 text-center text-sm font-semibold transition first:rounded-tl-xl last:rounded-tr-xl {{ $view === $timelineView ? 'border-slate-200 border-b-white bg-white text-slate-950 dark:border-slate-800 dark:border-b-slate-900 dark:bg-slate-900 dark:text-white' : 'border-slate-200 bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 dark:border-slate-800 dark:bg-slate-800/80 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <section id="requester-timeline-card" class="overflow-hidden rounded-b-2xl rounded-tr-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="project-timeline-title">
        <div class="border-b border-slate-200 p-5 dark:border-slate-800">
            <h3 id="project-timeline-title" class="text-lg font-bold">{{ $itemLabel }} timeline</h3>
            <p class="mt-1 text-xs text-slate-500">Klik {{ $itemLabelLower }} untuk melihat task terjadwal · progress berasal dari task selesai · timezone {{ $deliveryWorkspace->timezone }}</p>
        </div>

        @if ($timelineRows->isEmpty())
            <div class="p-5"><x-empty-state icon="calendar" title="Tidak ada {{ $itemLabelLower }} pada periode ini" description="Pilih skala yang lebih lebar untuk melihat jadwal {{ $itemLabelLower }} lainnya." /></div>
        @else
            <div class="max-h-[440px] overflow-auto" tabindex="0" role="region" aria-label="Scrollable {{ $itemLabelLower }} timeline">
                <div x-data="{ openProject: null }" class="relative grid grid-cols-[minmax(210px,260px)_minmax(0,1fr)]" style="min-width: {{ $timeline->minWidth() }}px">
                    <div class="sticky left-0 top-0 z-30 flex items-end border-b border-r border-slate-200 bg-white px-5 py-3 text-[11px] font-bold uppercase tracking-[.14em] text-slate-400 dark:border-slate-800 dark:bg-slate-900">{{ $itemLabel }}</div>
                    <div class="sticky top-0 z-20 h-14 border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-hidden="true">
                        @foreach ($ticks as $tick)<span class="absolute inset-y-0 border-l border-slate-200 dark:border-slate-800" style="left: {{ $tick['left'] }}%"><span class="absolute left-1 top-3 whitespace-nowrap text-[10px] text-slate-400">{{ $tick['label'] }}</span></span>@endforeach
                        @if ($todayPosition !== null)<span class="absolute inset-y-0 z-10 border-l-2 border-slate-900 dark:border-orbit-300" style="left: {{ $todayPosition }}%"><span class="absolute left-1 top-8 text-[10px] font-bold">Today</span></span>@endif
                    </div>
                    @foreach ($timelineRows as $project)
                        <button type="button" @click="openProject = openProject === '{{ $project['public_id'] }}' ? null : '{{ $project['public_id'] }}'" :aria-expanded="openProject === '{{ $project['public_id'] }}'" class="sticky left-0 z-20 flex min-w-0 items-center gap-3 border-b border-r border-slate-200 bg-white px-5 py-3 text-left hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-orbit-500 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800">
                            <svg class="size-4 shrink-0 text-slate-400 transition-transform" :class="openProject === '{{ $project['public_id'] }}' && 'rotate-90'" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m7.5 5 5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $project['color'] }}"></span>
                            <span class="min-w-0"><span class="block truncate text-sm font-semibold">{{ $project['name'] }}</span><span class="mt-1 block truncate text-[10px] text-slate-400">@if($project['context']){{ $project['context'] }} · @endif{{ $project['status'] }} · {{ $project['date_label'] }} · {{ $project['tasks']->count() }} task</span></span>
                        </button>
                        <button type="button" @click="openProject = openProject === '{{ $project['public_id'] }}' ? null : '{{ $project['public_id'] }}'" :aria-expanded="openProject === '{{ $project['public_id'] }}'" class="relative h-16 border-b border-slate-200 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-orbit-500 dark:border-slate-800" aria-label="Toggle {{ $project['name'] }} tasks">
                            @foreach ($ticks as $tick)<span class="absolute inset-y-0 border-l border-slate-100 dark:border-slate-800/80" style="left: {{ $tick['left'] }}%" aria-hidden="true"></span>@endforeach
                            @if ($todayPosition !== null)<span class="absolute inset-y-0 z-10 border-l-2 border-slate-900 dark:border-orbit-300" style="left: {{ $todayPosition }}%" aria-hidden="true"></span>@endif
                            <div class="absolute top-3 z-10 flex h-10 min-w-10 items-center overflow-hidden rounded-xl border px-3 shadow-sm" style="left: {{ $project['left'] }}%; width: {{ $project['width'] }}%; border-color: {{ $project['color'] }}; background-color: color-mix(in srgb, {{ $project['color'] }} 14%, white)" aria-label="{{ $project['name'] }}, {{ $project['progress'] }} percent complete">
                                <span class="absolute inset-y-0 left-0 opacity-20" style="width: {{ $project['progress'] }}%; background-color: {{ $project['color'] }}" aria-hidden="true"></span>
                                <span class="relative truncate text-[11px] font-semibold text-slate-800">{{ $project['name'] }}</span><span class="relative ml-auto pl-2 text-[10px] font-bold text-slate-800">{{ $project['progress'] }}%</span>
                            </div>
                        </button>

                        @forelse ($project['tasks'] as $task)
                            <div x-cloak x-show="openProject === '{{ $project['public_id'] }}'" class="sticky left-0 z-20 min-w-0 border-b border-r border-slate-200 bg-slate-50 py-3 pl-12 pr-5 dark:border-slate-800 dark:bg-slate-800/60">
                                <div class="flex items-center gap-2"><span class="size-2 shrink-0 rounded-full" style="background-color: {{ $task['color'] }}"></span><span class="truncate text-sm font-semibold">{{ $task['title'] }}</span></div>
                                <p class="mt-1 truncate text-[10px] text-slate-400">{{ $task['status'] }} · {{ $task['date_label'] }}</p>
                            </div>
                            <div x-cloak x-show="openProject === '{{ $project['public_id'] }}'" class="relative h-14 border-b border-slate-200 bg-slate-50/60 dark:border-slate-800 dark:bg-slate-800/30">
                                @foreach ($ticks as $tick)<span class="absolute inset-y-0 border-l border-slate-100 dark:border-slate-800/80" style="left: {{ $tick['left'] }}%" aria-hidden="true"></span>@endforeach
                                @if ($todayPosition !== null)<span class="absolute inset-y-0 z-10 border-l-2 border-slate-900 dark:border-orbit-300" style="left: {{ $todayPosition }}%" aria-hidden="true"></span>@endif
                                <div class="absolute top-2 z-10 flex h-10 min-w-10 items-center overflow-hidden rounded-xl border px-3 shadow-sm {{ $task['is_overdue'] ? 'ring-2 ring-rose-400' : '' }}" style="left: {{ $task['left'] }}%; width: {{ $task['width'] }}%; border-color: {{ $task['color'] }}; background-color: color-mix(in srgb, {{ $task['color'] }} 14%, white)" aria-label="{{ $task['title'] }}, {{ $task['progress'] }} percent complete{{ $task['is_overdue'] ? ', overdue' : '' }}">
                                    <span class="absolute inset-y-0 left-0 opacity-20" style="width: {{ $task['progress'] }}%; background-color: {{ $task['color'] }}" aria-hidden="true"></span>
                                    <span class="relative truncate text-[11px] font-semibold text-slate-800">{{ $task['title'] }}</span><span class="relative ml-auto pl-2 text-[10px] font-bold text-slate-800">{{ $task['progress'] }}%</span>
                                </div>
                            </div>
                        @empty
                            <div x-cloak x-show="openProject === '{{ $project['public_id'] }}'" class="sticky left-0 z-20 border-b border-r border-slate-200 bg-slate-50 py-4 pl-12 pr-5 text-xs font-semibold text-slate-400 dark:border-slate-800 dark:bg-slate-800/60">No scheduled tasks</div>
                            <div x-cloak x-show="openProject === '{{ $project['public_id'] }}'" class="flex h-14 items-center border-b border-slate-200 bg-slate-50/60 px-5 text-xs text-slate-400 dark:border-slate-800 dark:bg-slate-800/30">No scheduled task in this period.</div>
                        @endforelse
                    @endforeach
                </div>
            </div>
            @if ($hiddenTaskCount > 0)<p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500 dark:border-slate-800">{{ $hiddenTaskCount }} task lain tidak ditampilkan pada ringkasan ini.</p>@endif
        @endif
    </section>
@endsection
