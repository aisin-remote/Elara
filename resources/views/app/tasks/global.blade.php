@extends('layouts.app')

@section('title', $selectedMember->is(auth()->user()) ? 'My tasks' : $selectedMember->name."'s tasks")
@section('page-title', $selectedMember->is(auth()->user()) ? 'My tasks' : $selectedMember->name."'s tasks")

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500">{{ $workspace->name }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">{{ $selectedMember->is(auth()->user()) ? 'My tasks' : $selectedMember->name."'s tasks" }}</h2>
            <p class="mt-1 text-sm text-slate-500">Only work assigned to {{ $selectedMember->is(auth()->user()) ? 'you' : $selectedMember->name }} is shown.</p>
        </div>
    </div>
    @if ($selectedMember->is(auth()->user()))
        <nav class="mt-6 flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-800" aria-label="My task views">
            <a href="{{ route('app.tasks.index', $workspace) }}" class="shrink-0 border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">Personal</a>
            <a href="{{ route('app.tasks.index', [$workspace, 'view' => 'assigned']) }}" aria-current="page" class="shrink-0 border-b-2 border-orbit-600 px-4 py-3 text-sm font-semibold text-orbit-700 dark:text-orbit-300">Assigned work</a>
        </nav>
    @endif
    <nav class="mt-6 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Task filters">@foreach (['' => 'All', 'todo' => 'Outstanding / Pending', 'in_progress' => 'In Progress', 'completed' => 'Done', 'overdue' => 'Overdue', 'blocked' => 'Blocked'] as $value => $label)<a href="{{ request()->fullUrlWithQuery(['tab' => $value ?: null, 'page' => null]) }}" class="shrink-0 border-b-2 px-4 py-3 text-sm font-semibold {{ request('tab', '') === $value ? 'border-orbit-600 text-orbit-700' : 'border-transparent text-slate-500' }}">{{ $label }}</a>@endforeach</nav>
    <form method="GET" class="mt-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 lg:grid-cols-[1fr_repeat(2,180px)_auto] dark:border-slate-800 dark:bg-slate-900">
        @unless($selectedMember->is(auth()->user()))<input type="hidden" name="assignee" value="{{ $selectedMember->public_id }}">@endunless
        <div class="relative"><x-icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"/><x-input name="search" value="{{ request('search') }}" placeholder="Search task" class="pl-9" /></div>
        <x-select name="project"><option value="">All work</option>@foreach($projects->groupBy(fn($project) => $project->type->label()) as $group => $groupProjects)<optgroup label="{{ \Illuminate\Support\Str::plural($group) }}">@foreach($groupProjects as $project)<option value="{{ $project->public_id }}" @selected(request('project') === $project->public_id)>{{ $project->name }}</option>@endforeach</optgroup>@endforeach</x-select>
        <x-select name="priority"><option value="">All priorities</option>@foreach($priorities as $priority)<option value="{{ $priority->value }}" @selected(request('priority') === $priority->value)>{{ $priority->label() }}</option>@endforeach</x-select>
        <x-button variant="secondary">Filter</x-button>
    </form>
    <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-[900px] w-full text-left text-sm"><thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-900"><tr><th class="px-5 py-3">Task</th><th class="px-4 py-3">Project</th><th class="px-4 py-3">Assignee</th><th class="px-4 py-3">Priority</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Due date</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">@forelse($tasks as $task)<tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40"><td class="px-5 py-4"><div class="flex items-center gap-2"><a href="{{ route('app.tasks.show', $task) }}" class="font-semibold hover:text-orbit-700">{{ $task->title }}</a>@if($task->isBlocked())<span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-200">Blocked</span>@endif</div><p class="mt-1 max-w-xs truncate text-xs text-slate-500">{{ $task->description }}</p></td><td class="px-4 py-4"><span class="inline-flex items-center gap-2"><span class="size-2 rounded-full" style="background:{{ $task->project->color }}"></span>{{ $task->project->name }}</span></td><td class="px-4 py-4">{{ $task->assignees->pluck('first_name')->join(', ') ?: 'Unassigned' }}</td><td class="px-4 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold dark:bg-slate-800">{{ $task->priority->label() }}</span></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" style="color:{{ $task->status->color }};background:color-mix(in srgb, {{ $task->status->color }} 12%, transparent)">{{ $task->status->name }}</span></td><td class="px-4 py-4 {{ $task->due_at && !$task->completed_at && $task->due_at->isPast() ? 'font-semibold text-rose-600' : 'text-slate-500' }}">{{ $task->due_at?->format('M j, Y') ?? '—' }}</td></tr>@empty<tr><td colspan="6" class="px-5 py-14 text-center text-slate-500">No tasks match these filters.</td></tr>@endforelse</tbody></table>
    </div><div class="mt-5">{{ $tasks->links() }}</div>
@endsection
