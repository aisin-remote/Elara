@extends('layouts.app')

@section('title', 'System health')
@section('page-title', 'Settings')

@section('content')
    @php
        $cards = [
            ['organization', 'Organization directory', 'PostgreSQL connectivity and department source.'],
            ['user_sync', 'Organization users', 'Most recent local directory profile sync.'],
            ['database', 'Application database', 'Primary Elara data connection.'],
            ['storage', 'File storage', 'Write access and remaining local capacity.'],
            ['scheduler', 'Scheduler', 'Heartbeat written by schedule:run every minute.'],
            ['queue', 'Queue', 'Pending and failed background work.'],
            ['ai', 'AI breakdown', 'Latest task breakdown generation result.'],
            ['openai', 'OpenAI', 'Credential and model configuration.'],
            ['holidays', 'Holiday sync', 'Latest automatic holiday calendar sync.'],
            ['mapping', 'Department workspaces', 'Missing, duplicate, stale, or mismatched mappings.'],
        ];
        $statusStyle = fn (string $status): string => match ($status) {
            'healthy' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200',
            'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-200',
            'failed' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-200',
            default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        };
        $statusLabel = fn (string $status): string => match ($status) {
            'healthy' => 'Healthy',
            'warning' => 'Attention',
            'failed' => 'Unavailable',
            'disabled' => 'Disabled',
            default => 'Unknown',
        };
        $relativeTime = fn ($value): string => filled($value) ? \Illuminate\Support\Carbon::parse($value)->diffForHumans() : 'Never';
    @endphp

    <div>
        @include('app.settings._navigation')

        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-orbit-600 dark:text-orbit-300">Operational reliability</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">System health</h2>
                <p class="mt-2 max-w-3xl text-sm text-slate-500 dark:text-slate-400">Live diagnostics for directory sync, storage, queues, automation, and AI delivery. No credential values are exposed here.</p>
            </div>
            <span class="rounded-full bg-violet-100 px-3 py-1.5 text-xs font-bold text-violet-800 dark:bg-violet-950/60 dark:text-violet-200">Developer access</span>
        </div>

        @if (session('status'))
            <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
        @endif
        @if ($errors->has('system_health'))
            <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">{{ $errors->first('system_health') }}</div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5" aria-label="System health checks">
            @foreach ($cards as [$key, $title, $description])
                @php($item = $health[$key])
                <article class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="grid size-10 shrink-0 place-items-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1m0-12.8-2.1 2.1m-8.6 8.6-2.1 2.1"/><circle cx="12" cy="12" r="4"/></svg>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statusStyle($item['status']) }}">{{ $statusLabel($item['status']) }}</span>
                    </div>
                    <h3 class="mt-4 font-bold text-slate-950 dark:text-white">{{ $title }}</h3>
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $description }}</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $item['message'] }}</p>

                    @if ($key === 'queue')
                        <p class="mt-3 text-xs font-semibold text-slate-500">{{ $item['pending'] }} pending · {{ $item['failed'] }} failed</p>
                    @elseif ($key === 'organization' && isset($item['department_count']))
                        <p class="mt-3 text-xs font-semibold text-slate-500">{{ $item['department_count'] }} departments found</p>
                    @elseif ($key === 'storage')
                        <p class="mt-3 text-xs font-semibold text-slate-500">{{ $item['free_space'] }} free</p>
                    @elseif ($key === 'database' && isset($item['driver']))
                        <p class="mt-3 text-xs font-semibold uppercase text-slate-500">{{ $item['driver'] }} driver</p>
                    @elseif ($key === 'user_sync')
                        <p class="mt-3 text-xs font-semibold text-slate-500">{{ $item['managed_users'] }} managed users · {{ $relativeTime($item['last_run_at']) }}</p>
                    @elseif ($key === 'scheduler')
                        <p class="mt-3 text-xs font-semibold text-slate-500">Last heartbeat: {{ $relativeTime($item['last_seen_at']) }}</p>
                    @elseif ($key === 'ai')
                        <p class="mt-3 text-xs font-semibold text-slate-500">Success: {{ $relativeTime($item['last_success_at']) }} · Failure: {{ $relativeTime($item['last_failure_at']) }}</p>
                    @elseif ($key === 'openai')
                        <p class="mt-3 truncate text-xs font-semibold text-slate-500">{{ $item['model'] }}</p>
                    @elseif ($key === 'holidays')
                        <p class="mt-3 text-xs font-semibold text-slate-500">Last run: {{ $relativeTime($item['last_run_at']) }}</p>
                    @elseif ($key === 'mapping' && $item['issue_count'] > 0)
                        <ul class="mt-3 space-y-1 text-xs text-amber-700 dark:text-amber-300">
                            @foreach (array_slice($item['issues'], 0, 3) as $issue)
                                <li class="truncate" title="{{ $issue }}">• {{ $issue }}</li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @endforeach
        </section>

        <section class="mt-6 rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-6 dark:border-slate-800">
                <h3 class="text-lg font-bold">Operational actions</h3>
                <p class="mt-1 text-sm text-slate-500">Safe, explicit maintenance commands. Each action reports its actual result before returning here.</p>
            </div>
            <div class="grid gap-px bg-slate-200 md:grid-cols-2 xl:grid-cols-4 dark:bg-slate-800">
                @foreach ([
                    ['sync-users', 'Sync organization users', 'Refresh local identity data from PostgreSQL.'],
                    ['rebuild-memberships', 'Rebuild memberships', 'Reapply department workspace and role mapping.'],
                    ['drain-requests', 'Drain approved requests', 'Schedule approved work into available capacity.'],
                    ['integrity-check', 'Run integrity check', 'Inspect mappings and orphaned AI breakdowns.'],
                ] as [$action, $label, $description])
                    <form method="POST" action="{{ route('app.settings.system-health.run', [$workspace, $action]) }}" class="bg-white p-5 dark:bg-slate-900">
                        @csrf
                        <p class="font-semibold">{{ $label }}</p>
                        <p class="mt-1 min-h-10 text-xs leading-5 text-slate-500">{{ $description }}</p>
                        <button type="submit" class="mt-4 inline-flex items-center rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-orbit-400 hover:text-orbit-700 dark:border-slate-700 dark:text-slate-200 dark:hover:border-orbit-500 dark:hover:text-orbit-200">Run now</button>
                    </form>
                @endforeach
            </div>
            @if ($health['last_integrity_check'])
                <div class="border-t border-slate-200 px-6 py-4 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300">
                    <span class="font-semibold">Last integrity check:</span> {{ $health['last_integrity_check']['message'] }}
                </div>
            @endif
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-6 dark:border-slate-800">
                    <h3 class="text-lg font-bold">Failed queue jobs</h3>
                    <p class="mt-1 text-sm text-slate-500">The most recent failures can be returned to the queue individually.</p>
                </div>
                @if ($health['failed_jobs'] === [])
                    <p class="p-6 text-sm text-slate-500">No failed queue jobs.</p>
                @else
                    <div class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($health['failed_jobs'] as $job)
                            <div class="flex items-center justify-between gap-4 p-5">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold">{{ $job['name'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $job['queue'] }} · {{ $job['failed_at'] }}</p>
                                </div>
                                <form method="POST" action="{{ route('app.settings.system-health.run', [$workspace, 'retry-job']) }}">
                                    @csrf
                                    <input type="hidden" name="target" value="{{ $job['uuid'] }}">
                                    <button class="rounded-xl bg-orbit-500 px-3 py-2 text-sm font-bold text-slate-950 hover:bg-orbit-400">Retry</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-6 dark:border-slate-800">
                    <h3 class="text-lg font-bold">Failed AI breakdowns</h3>
                    <p class="mt-1 text-sm text-slate-500">Retry only the affected feature or project plan.</p>
                </div>
                @if ($health['failed_breakdowns'] === [])
                    <p class="p-6 text-sm text-slate-500">No failed AI breakdowns.</p>
                @else
                    <div class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($health['failed_breakdowns'] as $breakdown)
                            <div class="flex items-center justify-between gap-4 p-5">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold">{{ $breakdown['subject'] }}</p>
                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $breakdown['workspace'] }} · {{ $breakdown['error'] }}</p>
                                </div>
                                <form method="POST" action="{{ route('app.settings.system-health.run', [$workspace, 'retry-breakdown']) }}">
                                    @csrf
                                    <input type="hidden" name="target" value="{{ $breakdown['public_id'] }}">
                                    <button class="rounded-xl bg-violet-600 px-3 py-2 text-sm font-bold text-white hover:bg-violet-500">Retry AI</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <section x-data="{ copied: false }" class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold">Diagnostic report</h3>
                    <p class="mt-1 text-sm text-slate-500">A credential-free summary that can be pasted into an incident or support message.</p>
                </div>
                <button type="button" @click="navigator.clipboard.writeText($refs.report.value).then(() => { copied = true; setTimeout(() => copied = false, 1800) })" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200">
                    <span x-show="! copied">Copy report</span>
                    <span x-cloak x-show="copied">Copied</span>
                </button>
            </div>
            <textarea x-ref="report" readonly rows="14" class="mt-5 w-full resize-y rounded-2xl border border-slate-200 bg-slate-50 p-4 font-mono text-xs leading-6 text-slate-700 outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300">{{ $health['diagnostic_report'] }}</textarea>
        </section>
    </div>
@endsection
