@extends('layouts.app')

@section('title', $project->name)
@section('page-title', $project->name)

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><div class="flex items-center gap-3"><span class="size-3 rounded-full" style="background: {{ $project->color ?? '#64748b' }}"></span><span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold dark:bg-slate-800">{{ $project->status->label() }}</span></div><p class="mt-3 max-w-3xl text-sm leading-6 text-slate-500">{{ $project->description ?: 'No description yet.' }}</p></div>
        @can('update', $project)<x-link-button href="{{ route('app.projects.edit', $project) }}" variant="secondary">Edit project</x-link-button>@endcan
    </div>

    @include('app.projects._tabs', ['project' => $project])

    @if ($breakdown)
        <div class="mt-6">
            @include('app.approvals._breakdown', ['breakdown' => $breakdown])
        </div>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_380px] xl:items-start">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="overview-title">
            <h2 id="overview-title" class="text-lg font-bold">Overview</h2>
            @php
                // Keep the block form here: the inline parenthesised variant gets swallowed by
                // Blade's block regex, which runs to the next block terminator further down.
                $stat = 'rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60';
            @endphp
            <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="{{ $stat }}"><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Owner</dt><dd class="mt-1 truncate text-sm font-semibold">{{ $project->owner->name }}</dd></div>
                <div class="{{ $stat }}">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Members</dt>
                    <dd class="mt-1 flex h-5 items-center gap-2">
                        <span class="flex -space-x-1.5">
                            @foreach ($project->memberships->take(5) as $membership)
                                <x-avatar :src="filled($membership->user->avatar_path) ? route('internal.users.avatar', $membership->user) : null" :name="$membership->user->name" size="size-6" class="border-2 border-white dark:border-slate-900" />
                            @endforeach
                        </span>
                        @if ($project->memberships->count() > 5)
                            <span class="text-xs font-semibold text-slate-500">+{{ $project->memberships->count() - 5 }}</span>
                        @endif
                    </dd>
                </div>
                <div class="{{ $stat }}"><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Start date</dt><dd class="mt-1 truncate text-sm font-semibold">{{ $project->start_date?->format('M j, Y') ?? 'Not set' }}</dd></div>
                <div class="{{ $stat }}"><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Due date</dt><dd class="mt-1 truncate text-sm font-semibold">{{ $project->due_date?->format('M j, Y') ?? 'Not set' }}</dd></div>
            </dl>
            <div class="mt-8 rounded-2xl border border-slate-200 p-5 dark:border-slate-700">
                @if ($eligibleTaskCount === 0)
                    <h3 class="font-bold">Task progress</h3>
                    <div class="mt-3 rounded-2xl bg-slate-50 p-8 text-center dark:bg-slate-800/60">
                        <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-white text-slate-400 dark:bg-slate-900"><x-icon name="tasks" /></span>
                        <p class="mt-3 font-semibold">No tasks yet</p>
                        <p class="mt-1 text-sm text-slate-500">Progress starts counting as soon as this project has work in it.</p>
                        @can('create', [App\Models\Task::class, $project])
                            <x-link-button href="{{ route('app.projects.tasks', [$project->workspace, $project, 'create' => 1]) }}" class="mt-4"><x-icon name="plus" />Add first task</x-link-button>
                        @endcan
                    </div>
                @else
                    @php
                        $scheduleTone = match ($schedule['state'] ?? null) {
                            'complete' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
                            'overdue' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
                            'behind' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
                            default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                        };
                        $scheduleLabel = match ($schedule['state'] ?? null) {
                            'complete' => 'Complete',
                            'overdue' => 'Past due date',
                            'behind' => 'Behind schedule',
                            'on_track' => 'On track',
                            default => null,
                        };
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-bold">Task progress</h3>
                        <div class="flex flex-wrap gap-2">
                            @if ($scheduleLabel)
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $scheduleTone }}">{{ $scheduleLabel }}</span>
                            @endif
                            @php
                                $forecastTone = match ($forecast['state'] ?? null) {
                                    'complete' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
                                    'on_track' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300',
                                    'at_risk' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
                                    'late' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
                                    default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                                };
                            @endphp
                            @if (! empty($forecast['label']))
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $forecastTone }}">Forecast: {{ $forecast['label'] }}</span>
                            @endif
                        </div>
                    </div>
                    {{-- ponytail: ticks are one repeating-linear-gradient, not N span elements --}}
                    <div class="mt-3 flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-800/60" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Task progress">
                        <div class="relative h-7 flex-1 text-slate-200 dark:text-slate-600" style="background-image: repeating-linear-gradient(90deg, currentColor 0 3px, transparent 3px 7px)">
                            <div class="absolute inset-y-0 left-0 text-orbit-500 dark:text-orbit-400" style="width: {{ $progress }}%; background-image: repeating-linear-gradient(90deg, currentColor 0 3px, transparent 3px 7px)"></div>
                        </div>
                        <p class="shrink-0 text-lg font-bold tabular-nums">{{ $progress }}<span class="ml-0.5 text-xs font-semibold text-slate-400">%</span></p>
                    </div>
                    @if ($schedule)
                        <p class="mt-3 text-sm text-slate-500">
                            {{ $schedule['elapsed'] }}% of the timeline has passed ·
                            @if ($schedule['days_left'] < 0)
                                {{ abs($schedule['days_left']) }} {{ \Illuminate\Support\Str::plural('day',abs($schedule['days_left'])) }} overdue
                            @elseif ($schedule['days_left'] === 0)
                                due today
                            @else
                                {{ $schedule['days_left'] }} {{ \Illuminate\Support\Str::plural('day',$schedule['days_left']) }} left
                            @endif
                            @if (! empty($forecast['reason']))
                                · {{ $forecast['reason'] }}
                            @endif
                            @if (! empty($forecast['projected_finish']))
                                · projected {{ $forecast['projected_finish'] }}
                            @endif
                        </p>
                    @endif
                    @php
                        $bucketChips = [
                            ['todo', 'To do', $taskBuckets['todo'], 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'],
                            ['in_progress', 'In progress', $taskBuckets['in_progress'], 'bg-orbit-50 text-orbit-700 dark:bg-orbit-950/60 dark:text-orbit-300'],
                            ['completed', 'Completed', $taskBuckets['completed'], 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'],
                            ['overdue', 'Overdue', $overdueTaskCount, 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'],
                        ];
                    @endphp
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($bucketChips as [$tab, $label, $count, $tone])
                            @continue($count === 0)
                            <a href="{{ route('app.tasks.index', [$project->workspace, 'project' => $project->public_id, 'tab' => $tab]) }}" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition hover:opacity-80 {{ $tone }}">
                                <span class="tabular-nums">{{ $count }}</span>{{ $label }}
                            </a>
                        @endforeach
                    </div>
                    <p class="mt-4 text-sm text-slate-500">{{ $completedTaskCount }} of {{ $eligibleTaskCount }} eligible tasks completed.</p>
                @endif
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900 xl:sticky xl:top-24 xl:max-h-[calc(100vh-8rem)] xl:overflow-y-auto" aria-labelledby="project-team-title">
            <h2 id="project-team-title" class="text-lg font-bold">Project team</h2>
            <div class="mt-4 space-y-3">
                @foreach ($project->memberships as $membership)
                    <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800/70">
                        <div class="flex items-center gap-3">
                            <x-avatar :src="filled($membership->user->avatar_path) ? route('internal.users.avatar', $membership->user) : null" :name="$membership->user->name" size="size-9" />
                            <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ $membership->user->name }}</p><p class="truncate text-xs text-slate-500">{{ $membership->user->email }}</p></div>
                            <span class="shrink-0 text-xs font-semibold">{{ $membership->role->label() }}</span>
                        </div>
                        @can('manageMembers', $project)
                            @if ($membership->user_id !== $project->owner_id)
                                <form method="POST" action="{{ route('internal.project-members.update', [$project, $membership->user->public_id]) }}" class="mt-3 flex gap-2">@csrf @method('PATCH')<x-select name="role" class="min-w-0">@foreach (App\Enums\ProjectMemberRole::cases() as $role)<option value="{{ $role->value }}" @selected($membership->role === $role)>{{ $role->label() }}</option>@endforeach</x-select><x-button variant="secondary">Save</x-button></form>
                                <form method="POST" action="{{ route('internal.project-members.destroy', [$project, $membership->user->public_id]) }}" class="mt-2" onsubmit="return confirm('Remove this project member?')">@csrf @method('DELETE')<button class="text-xs font-semibold text-rose-600">Remove</button></form>
                            @endif
                        @endcan
                    </div>
                @endforeach
            </div>
            @can('manageMembers', $project)
                @if ($availableMembers->isNotEmpty())
                    <form method="POST" action="{{ route('internal.project-members.store', $project) }}" class="mt-5 border-t border-slate-200 pt-5 dark:border-slate-800">@csrf<h3 class="text-sm font-bold">Add workspace member</h3><div class="mt-3 space-y-3"><x-select name="member_public_id" required><option value="">Choose member</option>@foreach ($availableMembers as $membership)<option value="{{ $membership->user->public_id }}">{{ $membership->user->name }}</option>@endforeach</x-select><x-select name="role">@foreach ([App\Enums\ProjectMemberRole::MEMBER, App\Enums\ProjectMemberRole::VIEWER, App\Enums\ProjectMemberRole::MANAGER] as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</x-select><x-button class="w-full">Add member</x-button></div></form>
                @endif
            @endcan
        </section>
    </div>

    @can('delete', $project)
        <section class="mt-6 rounded-3xl border border-rose-200 bg-white p-6 dark:border-rose-900/60 dark:bg-slate-900" aria-labelledby="danger-zone-title">
            <h2 id="danger-zone-title" class="text-lg font-bold text-rose-700 dark:text-rose-300">Danger zone</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">Archiving hides this project and its tasks from the workspace. Nothing is deleted — you can restore it from the bottom of the projects list.</p>
            <x-button type="button" variant="danger" class="mt-4" onclick="document.getElementById('archive-project-dialog').showModal()">Archive project</x-button>
        </section>

        <x-modal id="archive-project-dialog" title="Archive {{ $project->name }}?">
            <p class="text-sm leading-6 text-slate-500">Members lose access to this project's tasks, files, and board until it is restored. {{ $eligibleTaskCount }} {{ \Illuminate\Support\Str::plural('task', $eligibleTaskCount) }} will be archived with it.</p>
            <div class="mt-6 flex justify-end gap-3">
                <form method="dialog"><x-button type="submit" variant="secondary">Cancel</x-button></form>
                <form method="POST" action="{{ route('internal.projects.destroy', $project) }}">@csrf @method('DELETE')<x-button variant="danger">Archive project</x-button></form>
            </div>
        </x-modal>
    @endcan
@endsection
