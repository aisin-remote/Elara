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

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px] xl:items-start">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="feature-tasks-title">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5 dark:border-slate-800">
                <div><h3 id="feature-tasks-title" class="font-bold">Feature tasks</h3><p class="mt-1 text-xs text-slate-500">Work scoped only to {{ $feature->name }}.</p></div>
                <span class="text-sm font-semibold tabular-nums">{{ $progress['completed'] }}/{{ $progress['total'] }} · {{ $progress['percentage'] }}%</span>
            </header>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($tasks as $task)
                    @include('app.features._task-row', ['task' => $task])
                @empty
                    <x-empty-state icon="tasks" title="No tasks yet" description="Add a task manually or accept this feature's AI plan." class="rounded-none border-0 shadow-none" />
                @endforelse
            </div>
        </section>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="feature-progress-title">
                <h3 id="feature-progress-title" class="font-bold">Progress</h3>
                <div class="mt-4 flex items-center gap-3" role="progressbar" aria-valuenow="{{ $progress['percentage'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Feature progress">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-orbit-500" style="width: {{ $progress['percentage'] }}%"></div></div>
                    <span class="text-sm font-bold tabular-nums">{{ $progress['percentage'] }}%</span>
                </div>
                <dl class="mt-5 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60"><dt class="text-[11px] text-slate-500">Tasks</dt><dd class="mt-1 font-bold">{{ $progress['total'] }}</dd></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60"><dt class="text-[11px] text-slate-500">Done</dt><dd class="mt-1 font-bold text-emerald-600">{{ $progress['completed'] }}</dd></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60"><dt class="text-[11px] text-slate-500">Overdue</dt><dd class="mt-1 font-bold text-rose-600">{{ $overdueTaskCount }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="feature-details-title">
                <h3 id="feature-details-title" class="font-bold">Details</h3>
                <dl class="mt-4 space-y-4 text-sm">
                    <div><dt class="text-xs text-slate-500">System</dt><dd class="mt-1 font-semibold">{{ $system->name }}</dd></div>
                    <div class="grid grid-cols-2 gap-3"><div><dt class="text-xs text-slate-500">Start date</dt><dd class="mt-1 font-semibold">{{ $feature->starts_at?->format('M j, Y') ?? 'Not set' }}</dd></div><div><dt class="text-xs text-slate-500">Due date</dt><dd class="mt-1 font-semibold">{{ $feature->due_at?->format('M j, Y') ?? 'Not set' }}</dd></div></div>
                    <div><dt class="text-xs text-slate-500">Team</dt><dd class="mt-2 flex -space-x-1.5">@forelse ($assignees->take(6) as $assignee)<x-avatar :src="filled($assignee->avatar_path) ? route('internal.users.avatar', $assignee) : null" :name="$assignee->name" size="size-8" class="border-2 border-white dark:border-slate-900" />@empty<span class="text-sm text-slate-400">Nobody assigned yet</span>@endforelse</dd></div>
                </dl>
            </section>
        </aside>
    </div>
@endsection
