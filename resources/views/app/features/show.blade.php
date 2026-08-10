@extends('layouts.app')

@section('title', $system->name)
@section('page-title', $system->name)

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500">Features / {{ $system->name }}</p>
            <div class="mt-2 flex items-center gap-3">
                <span class="size-3 rounded-full" style="background: {{ $system->color ?? '#64748b' }}"></span>
                <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold dark:bg-slate-800">System</span>
                @if ($pic = $system->pic())
                    <span class="flex items-center gap-2 text-xs text-slate-500">
                        <x-avatar :src="filled($pic->avatar_path) ? route('internal.users.avatar', $pic) : null" :name="$pic->name" size="size-6" />
                        PIC {{ $pic->name }}
                    </span>
                @endif
            </div>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-500">{{ $system->description ?: 'No description yet.' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-link-button href="{{ route('app.projects.board', [$workspace, $system]) }}" variant="secondary">Board</x-link-button>
            <x-link-button href="{{ route('app.projects.tasks', [$workspace, $system]) }}" variant="secondary">Task list</x-link-button>
            <x-link-button href="{{ route('app.projects.timeline', [$workspace, $system]) }}" variant="secondary">Timeline</x-link-button>
        </div>
    </div>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="system-progress-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 id="system-progress-title" class="font-bold">Overall progress</h3>
            <p class="text-sm text-slate-500">{{ $progress['completed'] }} of {{ $progress['total'] }} eligible tasks completed</p>
        </div>
        <div class="mt-3 flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-800/60" role="progressbar" aria-valuenow="{{ $progress['percentage'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="System progress">
            <div class="relative h-7 flex-1 text-slate-200 dark:text-slate-600" style="background-image: repeating-linear-gradient(90deg, currentColor 0 3px, transparent 3px 7px)">
                <div class="absolute inset-y-0 left-0 text-orbit-500 dark:text-orbit-400" style="width: {{ $progress['percentage'] }}%; background-image: repeating-linear-gradient(90deg, currentColor 0 3px, transparent 3px 7px)"></div>
            </div>
            <p class="shrink-0 text-lg font-bold tabular-nums">{{ $progress['percentage'] }}<span class="ml-0.5 text-xs font-semibold text-slate-400">%</span></p>
        </div>
    </section>

    <h3 class="mt-8 text-lg font-bold">Features</h3>
    <div class="mt-4 space-y-4">
        @forelse ($features as $feature)
            @php($featureProgress = $feature->progress())
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" x-data="{ open: true }">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5 dark:border-slate-800">
                    <button type="button" class="flex min-w-0 items-center gap-3 text-left" x-on:click="open = ! open" :aria-expanded="open">
                        <span class="text-slate-400" x-text="open ? '⌄' : '⌃'"></span>
                        <span class="min-w-0">
                            <span class="block truncate font-bold">{{ $feature->name }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">
                                {{ str($feature->status)->replace('_', ' ')->headline() }}
                                @if ($feature->due_at) · due {{ $feature->due_at->format('M j, Y') }} @endif
                                @if ($feature->archived_at) · <span class="font-semibold text-amber-600">Archived</span> @endif
                            </span>
                        </span>
                    </button>
                    <span class="shrink-0 text-sm font-semibold tabular-nums">{{ $featureProgress['completed'] }}/{{ $featureProgress['total'] }} · {{ $featureProgress['percentage'] }}%</span>
                </header>

                <div x-show="open" class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($feature->tasks as $task)
                        @include('app.features._task-row', ['task' => $task])
                    @empty
                        <p class="p-5 text-sm text-slate-500">No tasks yet. Tasks appear here once the request behind this feature is broken down.</p>
                    @endforelse
                </div>
            </section>
        @empty
            <x-empty-state
                icon="tasks"
                title="No features yet"
                description="Approved feature requests land here as features, each carrying the tasks produced for it." />
        @endforelse
    </div>

    @if ($looseTasks->isNotEmpty())
        <section class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="loose-tasks-title">
            <header class="border-b border-slate-200 p-5 dark:border-slate-800">
                <h3 id="loose-tasks-title" class="font-bold">Maintenance tasks</h3>
                <p class="mt-1 text-xs text-slate-500">Work on this system that belongs to no feature.</p>
            </header>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($looseTasks as $task)
                    @include('app.features._task-row', ['task' => $task])
                @endforeach
            </div>
        </section>
    @endif
@endsection
