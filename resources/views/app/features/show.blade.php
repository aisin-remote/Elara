@extends('layouts.app')

@section('title', $system->name)
@section('page-title', $system->name)

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="size-3 rounded-full" style="background: {{ $system->color ?? '#64748b' }}"></span>
                <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold dark:bg-slate-800">{{ $system->status->label() }}</span>
            </div>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-500">{{ $system->description ?: 'No description yet.' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('create', [App\Models\Project::class, $workspace])
                <x-link-button href="{{ route('app.features.create', ['workspace' => $workspace, 'system' => $system->public_id]) }}"><x-icon name="plus" />New feature</x-link-button>
            @endcan
        </div>
    </div>

    @include('app.features._tabs')

    @php
        $activeFeatureCount = $features->whereNull('archived_at')->count();
        $systemMembers = $system->members;
        $pic = $system->pic();
    @endphp

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_380px] xl:items-start">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="system-overview-title">
            <h2 id="system-overview-title" class="text-lg font-bold">Overview</h2>

            <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">PIC</dt><dd class="mt-1 truncate text-sm font-semibold">{{ $pic?->name ?? 'Not assigned' }}</dd></div>
                <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Members</dt>
                    <dd class="mt-1 flex h-5 items-center gap-2">
                        <span class="flex -space-x-1.5">
                            @foreach ($systemMembers->take(5) as $member)
                                <x-avatar :src="filled($member->avatar_path) ? route('internal.users.avatar', $member) : null" :name="$member->name" size="size-6" class="border-2 border-white dark:border-slate-900" />
                            @endforeach
                        </span>
                        @if ($systemMembers->count() > 5)
                            <span class="text-xs font-semibold text-slate-500">+{{ $systemMembers->count() - 5 }}</span>
                        @endif
                    </dd>
                </div>
                <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Start date</dt><dd class="mt-1 truncate text-sm font-semibold">{{ $system->start_date?->format('M j, Y') ?? 'Not set' }}</dd></div>
                <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Due date</dt><dd class="mt-1 truncate text-sm font-semibold">{{ $system->due_date?->format('M j, Y') ?? 'Not set' }}</dd></div>
            </dl>

            <div class="mt-8 rounded-2xl border border-slate-200 p-5 dark:border-slate-700">
                @if ($progress['total'] === 0)
                    <h3 class="font-bold">Task progress</h3>
                    <div class="mt-3 rounded-2xl bg-slate-50 p-8 text-center dark:bg-slate-800/60">
                        <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-white text-slate-400 dark:bg-slate-900"><x-icon name="tasks" /></span>
                        <p class="mt-3 font-semibold">No tasks yet</p>
                        <p class="mt-1 text-sm text-slate-500">Progress starts counting as soon as this system has work in it.</p>
                    </div>
                @else
                    <h3 class="font-bold">Task progress</h3>
                    <div class="mt-3 flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-800/60" role="progressbar" aria-valuenow="{{ $progress['percentage'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Task progress">
                        <div class="relative h-7 flex-1 text-slate-200 dark:text-slate-600" style="background-image: repeating-linear-gradient(90deg, currentColor 0 3px, transparent 3px 7px)">
                            <div class="absolute inset-y-0 left-0 text-orbit-500 dark:text-orbit-400" style="width: {{ $progress['percentage'] }}%; background-image: repeating-linear-gradient(90deg, currentColor 0 3px, transparent 3px 7px)"></div>
                        </div>
                        <p class="shrink-0 text-lg font-bold tabular-nums">{{ $progress['percentage'] }}<span class="ml-0.5 text-xs font-semibold text-slate-400">%</span></p>
                    </div>
                    @php
                        $bucketChips = [
                            ['todo', 'To do', $progress['buckets']['todo'], 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'],
                            ['in_progress', 'In progress', $progress['buckets']['in_progress'], 'bg-orbit-50 text-orbit-700 dark:bg-orbit-950/60 dark:text-orbit-300'],
                            ['completed', 'Completed', $progress['completed'], 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'],
                            ['overdue', 'Overdue', $progress['overdue'], 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'],
                        ];
                    @endphp
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($bucketChips as [$tab, $label, $count, $tone])
                            @continue($count === 0)
                            <a href="{{ route('app.projects.tasks', [$workspace, $system, 'tab' => $tab]) }}" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition hover:opacity-80 {{ $tone }}">
                                <span class="tabular-nums">{{ $count }}</span>{{ $label }}
                            </a>
                        @endforeach
                    </div>
                    <p class="mt-4 text-sm text-slate-500">{{ $progress['completed'] }} of {{ $progress['total'] }} eligible tasks completed.</p>
                @endif
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900 xl:sticky xl:top-24 xl:max-h-[calc(100vh-8rem)] xl:overflow-y-auto" aria-labelledby="system-team-title">
            <h2 id="system-team-title" class="text-lg font-bold">System team</h2>
            <div class="mt-4 space-y-3">
                @forelse ($systemMembers as $member)
                    <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800/70">
                        <div class="flex items-center gap-3">
                            <x-avatar :src="filled($member->avatar_path) ? route('internal.users.avatar', $member) : null" :name="$member->name" size="size-9" />
                            <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ $member->name }}</p><p class="truncate text-xs text-slate-500">{{ $member->email }}</p></div>
                            <span class="shrink-0 text-xs font-semibold">{{ App\Enums\ProjectMemberRole::tryFrom($member->pivot->role)?->label() }}</span>
                        </div>
                    </div>
                @empty
                    <p class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm text-slate-500 dark:border-slate-700">No system members yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="feature-portfolio-title">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5 dark:border-slate-800"><div><h2 id="feature-portfolio-title" class="text-lg font-bold">Feature portfolio</h2><p class="mt-1 text-sm text-slate-500">Progress and ownership for every feature in {{ $system->name }}.</p></div><span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $activeFeatureCount }} active</span></header>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] text-left text-sm">
                <thead class="bg-slate-50/80 text-[11px] uppercase tracking-[.1em] text-slate-400 dark:bg-slate-900"><tr><th class="px-5 py-3">Feature</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Schedule</th><th class="px-4 py-3">Task health</th><th class="px-4 py-3">Progress</th><th class="px-4 py-3">Team</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($features as $feature)
                        @php
                            $featureTasks = $feature->tasks;
                            $eligibleTasks = $featureTasks->reject(fn ($task) => $task->status->category === App\Enums\TaskStatusCategory::CANCELLED);
                            $completedCount = $eligibleTasks->whereNotNull('completed_at')->count();
                            $featureProgress = $eligibleTasks->count() ? (int) round($completedCount / $eligibleTasks->count() * 100) : 0;
                            $overdueCount = $eligibleTasks->filter(fn ($task) => ! $task->completed_at && $task->due_at?->isPast())->count();
                            $featureAssignees = $featureTasks->flatMap(fn ($task) => $task->assignees)->unique('id')->values();
                            $breakdown = $feature->breakdowns->first();
                            $aiLabel = $breakdown && in_array($breakdown->status->value, ['pending', 'ready', 'failed'], true)
                                ? match ($breakdown->status->value) { 'ready' => 'AI plan ready for review', 'failed' => 'AI plan needs attention', default => 'AI plan is generating' }
                                : null;
                        @endphp
                        <tr class="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                            <td class="max-w-sm px-5 py-4"><a href="{{ route('app.features.detail', [$workspace, $system, $feature]) }}" class="font-bold hover:text-orbit-700 dark:hover:text-orbit-300">{{ $feature->name }}</a><p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ $feature->description ?: 'No description yet.' }}</p>@if($aiLabel)<a href="{{ route('app.features.detail', [$workspace, $system, $feature]) }}" class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-violet-700 dark:text-violet-300"><x-icon name="sparkles" class="size-3" />{{ $aiLabel }}</a>@endif</td>
                            <td class="px-4 py-4"><span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-950/60 dark:text-sky-300">{{ str($feature->status)->replace('_', ' ')->headline() }}</span>@if($feature->archived_at)<p class="mt-2 text-[11px] font-semibold text-amber-600">Archived</p>@endif</td>
                            <td class="whitespace-nowrap px-4 py-4 text-xs"><p class="font-semibold">{{ $feature->starts_at?->format('M j, Y') ?? 'No start' }}</p><p class="mt-1 text-slate-500">to {{ $feature->due_at?->format('M j, Y') ?? 'No deadline' }}</p></td>
                            <td class="px-4 py-4"><p class="font-semibold tabular-nums">{{ $completedCount }}/{{ $eligibleTasks->count() }} completed</p><p class="mt-1 text-xs {{ $overdueCount ? 'font-semibold text-rose-600 dark:text-rose-400' : 'text-slate-500' }}">{{ $overdueCount }} overdue</p></td>
                            <td class="w-40 px-4 py-4"><div class="flex items-center gap-3"><div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-orbit-500" style="width: {{ $featureProgress }}%"></div></div><span class="text-xs font-bold tabular-nums">{{ $featureProgress }}%</span></div></td>
                            <td class="px-4 py-4"><div class="flex -space-x-1.5">@forelse($featureAssignees->take(4) as $assignee)<x-avatar :src="filled($assignee->avatar_path) ? route('internal.users.avatar', $assignee) : null" :name="$assignee->name" size="size-7" class="border-2 border-white dark:border-slate-900" />@empty<span class="text-xs text-slate-400">Unassigned</span>@endforelse</div></td>
                            <td class="px-5 py-4"><div class="flex justify-end gap-2"><a href="{{ route('app.features.detail', [$workspace, $system, $feature]) }}" class="inline-flex min-h-9 items-center rounded-lg border border-slate-200 px-3 text-xs font-bold transition hover:border-orbit-300 hover:text-orbit-700 dark:border-slate-700 dark:hover:text-orbit-300">Open</a>@can('create', [App\Models\Task::class, $system])<a href="{{ route('app.projects.tasks', ['workspace' => $workspace, 'project' => $system, 'create' => 1, 'feature' => $feature->public_id]) }}" class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-orbit-600 px-3 text-xs font-bold text-white transition hover:bg-orbit-700"><x-icon name="plus" class="size-3.5" />Task</a>@endcan</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center"><p class="font-semibold">No features yet</p><p class="mt-1 text-sm text-slate-500">Features created by IT or approved from requester submissions appear here.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

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
