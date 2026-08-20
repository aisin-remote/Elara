@extends('layouts.app')

@section('title', 'Supporting')
@section('page-title', 'Supporting')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500">{{ $workspace->name }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">Operational work outside delivery projects</h2>
            <p class="mt-1 text-sm text-slate-500">Register hardware, software, network, and other supporting work here.</p>
        </div>
        @can('create', [App\Models\SupportingTask::class, $workspace])
            <x-link-button href="{{ route('app.supporting.create', $workspace) }}"><x-icon name="plus" />Add supporting task</x-link-button>
        @endcan
    </div>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Supporting task summary">
        @foreach ([
            ['open', 'Open work', 'tasks', 'bg-orbit-50 text-orbit-700 dark:bg-orbit-950 dark:text-orbit-300'],
            ['in_progress', 'In progress', 'clock', 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'],
            ['overdue', 'Overdue', 'warning', 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300'],
            ['completed', 'Completed', 'check', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'],
        ] as [$key, $label, $icon, $tone])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-3">
                    <div><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold">{{ number_format($stats[$key]) }}</p></div>
                    <span class="grid size-11 place-items-center rounded-xl {{ $tone }}"><x-icon :name="$icon" /></span>
                </div>
            </article>
        @endforeach
    </section>

    <form method="GET" class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 lg:grid-cols-[minmax(220px,1fr)_repeat(3,minmax(150px,190px))_auto] dark:border-slate-800 dark:bg-slate-900">
        <div class="relative"><x-icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><x-input name="search" value="{{ request('search') }}" placeholder="Search supporting task" class="pl-9" aria-label="Search supporting task" /></div>
        <x-select name="status" aria-label="Filter by status"><option value="">All statuses</option>@foreach ($statuses as $option)<option value="{{ $option->value }}" @selected(request('status') === $option->value)>{{ $option->label() }}</option>@endforeach</x-select>
        <x-select name="category" aria-label="Filter by category"><option value="">All categories</option>@foreach ($categories as $option)<option value="{{ $option->value }}" @selected(request('category') === $option->value)>{{ $option->label() }}</option>@endforeach</x-select>
        <x-select name="assignee" aria-label="Filter by assignee"><option value="">All assignees</option>@foreach ($members as $membership)<option value="{{ $membership->user->public_id }}" @selected(request('assignee') === $membership->user->public_id)>{{ $membership->user->name }}</option>@endforeach</x-select>
        <div class="flex gap-2"><x-button variant="secondary">Filter</x-button>@if(request()->hasAny(['search', 'status', 'category', 'assignee']))<x-link-button href="{{ route('app.supporting.index', $workspace) }}" variant="secondary">Clear</x-link-button>@endif</div>
    </form>

    <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full min-w-[920px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-900"><tr><th class="px-5 py-3">Task</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Assignee</th><th class="px-4 py-3">Priority</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Due date</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($tasks as $task)
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                        <td class="px-5 py-4"><a href="{{ route('app.supporting.edit', [$workspace, $task]) }}" class="font-semibold hover:text-orbit-700 dark:hover:text-orbit-300">{{ $task->title }}</a><p class="mt-1 max-w-xs truncate text-xs text-slate-500">{{ $task->description ?: 'No description' }}</p></td>
                        <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $task->category->label() }}</td>
                        <td class="px-4 py-4">@if($task->assignee)<span class="inline-flex items-center gap-2"><x-avatar :src="filled($task->assignee->avatar_path) ? route('internal.users.avatar', $task->assignee) : null" :name="$task->assignee->name" size="size-7" />{{ $task->assignee->name }}</span>@else<span class="text-slate-400">Unassigned</span>@endif</td>
                        <td class="px-4 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold dark:bg-slate-800">{{ $task->priority->label() }}</span></td>
                        <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $task->status->badgeClasses() }}">{{ $task->status->label() }}</span></td>
                        <td class="px-4 py-4 {{ $task->isOverdue() ? 'font-semibold text-rose-600 dark:text-rose-400' : 'text-slate-500' }}">{{ $task->due_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-5 py-4 text-right"><a href="{{ route('app.supporting.edit', [$workspace, $task]) }}" class="text-xs font-bold text-orbit-700 hover:text-orbit-800 dark:text-orbit-300">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-14"><x-empty-state icon="supporting" title="No supporting tasks found" description="Add operational work that does not belong to a project, system, or feature." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $tasks->links() }}</div>
@endsection
