@extends('layouts.app')

@section('title', $feature->name)
@section('page-title', $feature->name)

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <nav class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500" aria-label="Breadcrumb">
                <a href="{{ route('app.features.index', $workspace) }}" class="hover:text-orbit-700">Projects</a><span>/</span>
                <a href="{{ route('app.features.show', [$workspace, $system]) }}" class="hover:text-orbit-700">{{ $system->name }}</a><span>/</span>
                <span class="truncate" aria-current="page">{{ $feature->name }}</span>
            </nav>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-bold tracking-tight">Feature overview</h2>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold dark:bg-slate-800">{{ str($feature->status)->replace('_', ' ')->headline() }}</span>
            </div>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">{{ $feature->description ?: 'No description yet.' }}</p>
        </div>
        @can('create', [App\Models\Task::class, $system])
            <x-link-button href="{{ route('app.projects.tasks', ['workspace' => $workspace, 'project' => $system, 'create' => 1, 'feature' => $feature->public_id]) }}"><x-icon name="plus" />Add task</x-link-button>
        @endcan
    </div>

    @include('app.features._tabs', ['feature' => $feature])

    @if ($breakdown && $breakdown->status->value !== 'accepted')
        <div class="mt-6">
            @include('app.approvals._breakdown', ['breakdown' => $breakdown])
        </div>
    @endif

    <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="feature-overview-title">
        <h2 id="feature-overview-title" class="text-lg font-bold">Overview</h2>

        <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">System</dt>
                <dd class="mt-1 truncate text-sm font-semibold"><a href="{{ route('app.features.show', [$workspace, $system]) }}" class="hover:text-orbit-700 dark:hover:text-orbit-300">{{ $system->name }}</a></dd>
            </div>
            <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Members</dt>
                <dd class="mt-1 flex h-5 items-center gap-2">
                    @if ($assignees->isEmpty())
                        <span class="truncate text-sm font-semibold text-slate-400">Unassigned</span>
                    @else
                        <span class="flex -space-x-1.5">
                            @foreach ($assignees->take(5) as $assignee)
                                <x-avatar :src="filled($assignee->avatar_path) ? route('internal.users.avatar', $assignee) : null" :name="$assignee->name" size="size-6" class="border-2 border-white dark:border-slate-900" />
                            @endforeach
                        </span>
                        @if ($assignees->count() > 5)
                            <span class="text-xs font-semibold text-slate-500">+{{ $assignees->count() - 5 }}</span>
                        @endif
                    @endif
                </dd>
            </div>
            <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Start date</dt>
                <dd class="mt-1 truncate text-sm font-semibold">{{ $feature->starts_at?->format('M j, Y') ?? 'Not set' }}</dd>
            </div>
            <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Due date</dt>
                <dd class="mt-1 truncate text-sm font-semibold">{{ $feature->due_at?->format('M j, Y') ?? 'Not set' }}</dd>
            </div>
        </dl>

        <div class="mt-8 rounded-2xl border border-slate-200 p-5 dark:border-slate-700">
            <h3 class="font-bold">Task progress</h3>
            @if ($progress['total'] === 0)
                <div class="mt-3 rounded-2xl bg-slate-50 p-8 text-center dark:bg-slate-800/60">
                    <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-white text-slate-400 dark:bg-slate-900"><x-icon name="tasks" /></span>
                    <p class="mt-3 font-semibold">No tasks yet</p>
                    <p class="mt-1 text-sm text-slate-500">Progress starts counting as soon as this feature has work in it.</p>
                    @can('create', [App\Models\Task::class, $system])
                        <x-link-button href="{{ route('app.projects.tasks', ['workspace' => $workspace, 'project' => $system, 'create' => 1, 'feature' => $feature->public_id]) }}" class="mt-4"><x-icon name="plus" />Add first task</x-link-button>
                    @endcan
                </div>
            @else
                <div class="mt-3 flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-800/60" role="progressbar" aria-valuenow="{{ $progress['percentage'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Feature progress">
                    <div class="relative h-7 flex-1 text-slate-200 dark:text-slate-600" style="background-image: repeating-linear-gradient(90deg, currentColor 0 3px, transparent 3px 7px)">
                        <div class="absolute inset-y-0 left-0 text-orbit-500 dark:text-orbit-400" style="width: {{ $progress['percentage'] }}%; background-image: repeating-linear-gradient(90deg, currentColor 0 3px, transparent 3px 7px)"></div>
                    </div>
                    <p class="shrink-0 text-lg font-bold tabular-nums">{{ $progress['percentage'] }}<span class="ml-0.5 text-xs font-semibold text-slate-400">%</span></p>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $progress['total'] - $progress['completed'] }} remaining</span>
                    <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">{{ $progress['completed'] }} done</span>
                    @if ($overdueTaskCount > 0)
                        <span class="rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">{{ $overdueTaskCount }} overdue</span>
                    @endif
                </div>
                <p class="mt-4 text-sm text-slate-500">{{ $progress['completed'] }} of {{ $progress['total'] }} eligible tasks completed.</p>
            @endif
        </div>
    </section>

@endsection
