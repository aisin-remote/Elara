@extends('layouts.marketing')

@section('title', 'Department delivery overview')

@section('content')
    @php($ticks = $timeline->ticks())
    @php($todayPosition = $timeline->todayPosition())
    @php($showingFeatures = $view === 'features')
    @php($itemLabel = $showingFeatures ? 'Feature' : 'Project')
    @php($itemLabelLower = strtolower($itemLabel))

    <section id="delivery" class="relative overflow-hidden border-b border-slate-200 dark:border-slate-800">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_10%,_rgba(46,176,251,0.16),_transparent_32%),radial-gradient(circle_at_85%_20%,_rgba(99,102,241,0.14),_transparent_30%)]"></div>
        <div class="relative mx-auto max-w-7xl px-5 py-16 lg:px-8 lg:py-20">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="inline-flex items-center gap-2 rounded-full border border-orbit-200 bg-orbit-50 px-3 py-1 text-sm font-semibold text-orbit-800 dark:border-orbit-900 dark:bg-orbit-950/60 dark:text-orbit-200">
                        <span class="size-2 rounded-full bg-emerald-500"></span>Live delivery overview
                    </p>
                    <h1 class="mt-5 text-4xl font-bold tracking-[-0.04em] text-slate-950 sm:text-5xl dark:text-white">
                        @if ($department)
                            {{ $department['code'] }} delivery, at a glance.
                        @else
                            See how company initiatives are moving forward.
                        @endif
                    </h1>
                    <p class="mt-5 max-w-2xl text-base leading-7 text-slate-600 dark:text-slate-300">
                        @if ($department)
                            This device remembers the last signed-in department. Only public schedule, status, and progress information is shown here.
                        @else
                            This device has not been personalized yet. Sign in once and Elara will remember the department timeline for future guest visits.
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @auth
                        <x-link-button href="{{ auth()->user()->homePath() }}">Open workspace</x-link-button>
                    @else
                        <x-link-button href="{{ route('login') }}">Log in to Elara</x-link-button>
                        @if ($department)
                            <form method="POST" action="{{ route('home.forget-department') }}">
                                @csrf
                                <x-button variant="secondary">Forget this device</x-button>
                            </form>
                        @endif
                    @endauth
                </div>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-3">
                @foreach ([
                    [($department ? 'Department ' : 'Company ').strtolower($itemLabel).'s', $itemCount, $showingFeatures ? 'sparkles' : 'projects'],
                    ['Scheduled work', $scheduledTaskCount, 'tasks'],
                    ['Average progress', $averageProgress.'%', 'performance'],
                ] as [$label, $value, $icon])
                    <article class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/90">
                        <div class="flex items-center justify-between gap-4">
                            <div><p class="text-xs font-semibold uppercase tracking-[.12em] text-slate-400">{{ $label }}</p><p class="mt-2 text-3xl font-bold tabular-nums">{{ $value }}</p></div>
                            <span class="grid size-11 place-items-center rounded-xl bg-orbit-50 text-orbit-700 dark:bg-orbit-950 dark:text-orbit-300"><x-icon :name="$icon" /></span>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="timeline" class="mx-auto max-w-7xl px-5 py-14 lg:px-8 lg:py-20" aria-labelledby="public-timeline-title">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[.14em] text-orbit-600">{{ $itemLabel }} roadmap</p>
                <h2 id="public-timeline-title" class="mt-2 text-3xl font-bold tracking-tight">{{ $department ? $department['code'].' timeline' : 'Personalize this device' }}</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                    @if ($department)
                        Monthly schedule with public {{ $itemLabelLower }} status and completion progress. Task details, people, requests, and documents remain private.
                    @else
                        Login once on this browser to show the sanitized department timeline on future visits.
                    @endif
                </p>
            </div>
            <p class="text-xs font-semibold text-slate-400">Updated {{ $updatedAt->format('d M Y, H:i') }}</p>
        </div>

        <nav class="relative z-10 mt-8 flex w-fit items-end" aria-label="Timeline view" role="tablist" data-timeline-tabs>
            @foreach (['projects' => 'Projects', 'features' => 'Features'] as $timelineView => $label)
                <a href="{{ request()->fullUrlWithQuery(['view' => $timelineView]) }}" role="tab" aria-selected="{{ $view === $timelineView ? 'true' : 'false' }}" aria-controls="public-timeline-card"
                    class="-mb-px -mr-px min-w-28 border px-5 py-3 text-center text-sm font-semibold transition first:rounded-tl-xl last:rounded-tr-xl {{ $view === $timelineView ? 'border-slate-200 border-b-white bg-white text-slate-950 dark:border-slate-800 dark:border-b-slate-900 dark:bg-slate-900 dark:text-white' : 'border-slate-200 bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 dark:border-slate-800 dark:bg-slate-800/80 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}">{{ $label }}</a>
            @endforeach
        </nav>

        @if (! $department)
            <div id="public-timeline-card" class="rounded-b-3xl rounded-tr-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-14 text-center dark:border-slate-700 dark:bg-slate-900/50">
                <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-white text-orbit-700 shadow-sm dark:bg-slate-800 dark:text-orbit-300"><x-icon name="calendar" /></span>
                <h3 class="mt-5 text-lg font-bold">No department remembered on this device</h3>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">Your first successful login securely stores only a department hint for 30 days. No account or authorization data is stored in this preference.</p>
                <x-link-button href="{{ route('login') }}" class="mt-5">Login to personalize</x-link-button>
            </div>
        @elseif (! $deliveryWorkspace || $timelineRows->isEmpty())
            <div id="public-timeline-card" class="rounded-b-3xl rounded-tr-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><x-empty-state icon="calendar" title="No {{ $itemLabelLower }}s in this period" description="There is no public {{ $itemLabelLower }} schedule for this department in the current monthly window." /></div>
        @else
            <div id="public-timeline-card" class="overflow-hidden rounded-b-3xl rounded-tr-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="max-h-[430px] overflow-auto" tabindex="0" role="region" aria-label="Scrollable department {{ $itemLabelLower }} timeline">
                    <div class="relative grid grid-cols-[minmax(210px,260px)_minmax(0,1fr)]" style="min-width: {{ $timeline->minWidth() }}px">
                        <div class="sticky left-0 top-0 z-30 flex items-end border-b border-r border-slate-200 bg-white px-5 py-3 text-[11px] font-bold uppercase tracking-[.14em] text-slate-400 dark:border-slate-800 dark:bg-slate-900">{{ $itemLabel }}</div>
                        <div class="sticky top-0 z-20 h-14 border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-hidden="true">
                            @foreach ($ticks as $tick)
                                <span class="absolute inset-y-0 border-l border-slate-200 dark:border-slate-800" style="left: {{ $tick['left'] }}%"><span class="absolute left-1 top-3 whitespace-nowrap text-[10px] text-slate-400">{{ $tick['label'] }}</span></span>
                            @endforeach
                            @if ($todayPosition !== null)<span class="absolute inset-y-0 z-10 border-l-2 border-orbit-500" style="left: {{ $todayPosition }}%"><span class="absolute left-1 top-8 text-[10px] font-bold text-orbit-700 dark:text-orbit-300">Today</span></span>@endif
                        </div>

                        @foreach ($timelineRows as $project)
                            <div class="sticky left-0 z-20 flex min-w-0 items-center gap-3 border-b border-r border-slate-200 bg-white px-5 py-4 dark:border-slate-800 dark:bg-slate-900">
                                <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $project['color'] }}"></span>
                                <span class="min-w-0"><span class="block truncate text-sm font-semibold">{{ $project['name'] }}</span><span class="mt-1 block truncate text-[10px] text-slate-400">{{ $project['status'] }} · {{ $project['date_label'] }}</span></span>
                            </div>
                            <div class="relative h-16 border-b border-slate-200 dark:border-slate-800">
                                @foreach ($ticks as $tick)<span class="absolute inset-y-0 border-l border-slate-100 dark:border-slate-800/80" style="left: {{ $tick['left'] }}%" aria-hidden="true"></span>@endforeach
                                @if ($todayPosition !== null)<span class="absolute inset-y-0 z-10 border-l-2 border-orbit-500" style="left: {{ $todayPosition }}%" aria-hidden="true"></span>@endif
                                <div class="absolute top-3 z-10 flex h-10 min-w-10 items-center overflow-hidden rounded-xl border px-3 shadow-sm" style="left: {{ $project['left'] }}%; width: {{ $project['width'] }}%; border-color: {{ $project['color'] }}; background-color: color-mix(in srgb, {{ $project['color'] }} 14%, white)" aria-label="{{ $project['name'] }}, {{ $project['progress'] }} percent complete">
                                    <span class="absolute inset-y-0 left-0 opacity-20" style="width: {{ $project['progress'] }}%; background-color: {{ $project['color'] }}" aria-hidden="true"></span>
                                    <span class="relative truncate text-[11px] font-semibold text-slate-800">{{ $project['name'] }}</span><span class="relative ml-auto pl-2 text-[10px] font-bold text-slate-800">{{ $project['progress'] }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <span>Showing up to 5 {{ $itemLabelLower }}s · timezone {{ $deliveryWorkspace->timezone }}</span>
                    @auth<a href="{{ auth()->user()->homePath() }}" class="font-bold text-orbit-700 hover:text-orbit-800 dark:text-orbit-300">Open complete workspace →</a>@else<a href="{{ route('login') }}" class="font-bold text-orbit-700 hover:text-orbit-800 dark:text-orbit-300">Login for full access →</a>@endauth
                </div>
            </div>
        @endif
    </section>

    <section class="border-t border-slate-200 bg-slate-50 py-12 dark:border-slate-800 dark:bg-slate-900/40" aria-labelledby="privacy-title">
        <div class="mx-auto flex max-w-7xl flex-col gap-5 px-5 sm:flex-row sm:items-center sm:justify-between lg:px-8">
            <div><h2 id="privacy-title" class="text-lg font-bold">A timeline is public. The work behind it is not.</h2><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">This homepage never exposes task titles, member identities, request descriptions, custom properties, files, meeting notes, or internal links.</p></div>
            <a href="{{ route('legal.privacy') }}" class="shrink-0 text-sm font-bold text-orbit-700 dark:text-orbit-300">Read privacy notice →</a>
        </div>
    </section>
@endsection
