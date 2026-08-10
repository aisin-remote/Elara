@extends('layouts.app')

@section('title', 'Portfolio')
@section('page-title', 'Portfolio')

@section('content')
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Delivery portfolio</h2>
                    <p class="mt-1 text-sm text-slate-500">Forecast health, blocked work, and critical path across projects you can see.</p>
                </div>
            </div>
            <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['Projects', $portfolio['summary']['projects'], 'text-slate-900 dark:text-white'],
                    ['On track', $portfolio['summary']['on_track'], 'text-emerald-700 dark:text-emerald-300'],
                    ['At risk', $portfolio['summary']['at_risk'], 'text-amber-700 dark:text-amber-300'],
                    ['Late', $portfolio['summary']['late'], 'text-rose-700 dark:text-rose-300'],
                ] as [$label, $value, $tone])
                    <div class="rounded-2xl bg-slate-50 px-4 py-4 dark:bg-slate-800/60">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums {{ $tone }}">{{ $value }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-sm text-slate-500">{{ $portfolio['summary']['blocked_tasks'] }} blocked · {{ $portfolio['summary']['critical_tasks'] }} on a critical path</p>
        </section>

        @if ($portfolio['latest_insight'])
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="insight-title">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 id="insight-title" class="font-bold">Weekly insight</h3>
                    <span class="text-xs font-semibold text-slate-400">{{ $portfolio['latest_insight']['period_start'] }} → {{ $portfolio['latest_insight']['period_end'] }} · {{ $portfolio['latest_insight']['source'] }}</span>
                </div>
                <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $portfolio['latest_insight']['summary'] }}</p>
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="font-bold">Projects & systems</h3>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($portfolio['projects'] as $row)
                    @php
                        $tone = match ($row['forecast']['state']) {
                            'complete' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
                            'on_track' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
                            'at_risk' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
                            default => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200',
                        };
                    @endphp
                    <a href="{{ route('app.projects.show', ['project' => $row['public_id']]) }}" class="flex flex-wrap items-center gap-4 px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $row['color'] ?? '#64748b' }}"></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-semibold text-slate-900 dark:text-white">{{ $row['name'] }}</p>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $tone }}">{{ $row['forecast']['label'] }}</span>
                                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $row['type'] }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ $row['forecast']['reason'] }}</p>
                        </div>
                        <div class="flex gap-4 text-right text-xs font-semibold tabular-nums text-slate-500">
                            <span>{{ $row['forecast']['progress'] }}%</span>
                            <span>{{ $row['forecast']['blocked'] }} blocked</span>
                            <span>{{ $row['forecast']['critical'] }} critical</span>
                            <span>{{ $row['forecast']['projected_finish'] ?? '—' }}</span>
                        </div>
                    </a>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-500">No visible projects yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
