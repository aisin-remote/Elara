@extends('layouts.requester')

@section('title', $task->title)
@section('page-title', 'Supporting')

@section('content')
    <a href="{{ route('desk.index', ['tab' => 'supporting']) }}" class="text-sm font-semibold text-orbit-700 dark:text-orbit-300">← All requests</a>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-6 dark:border-slate-800">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Supporting · {{ $task->category->label() }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">{{ $task->title }}</h2>
            <p class="mt-2 text-sm text-slate-500">
                Submitted {{ $task->created_at->format('M j, Y') }}
                @if ($task->creator)
                    by {{ $task->creator->name }}
                @endif
                @if ($task->requester_department_name)
                    · {{ $task->requester_department_name }}
                @endif
            </p>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $task->status->badgeClasses() }}">{{ $task->status->label() }}</span>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Details</h3>
            <p class="mt-3 whitespace-pre-line text-sm leading-6">{{ $task->description }}</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="supporting-details-title">
            <h3 id="supporting-details-title" class="font-bold">Request details</h3>
            <dl class="mt-5 space-y-4 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Category</dt>
                    <dd class="mt-1 font-semibold">{{ $task->category->label() }}</dd>
                </div>
                <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Priority</dt>
                    <dd class="mt-1 font-semibold">{{ $task->priority->label() }}</dd>
                </div>
                <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Assigned to</dt>
                    <dd class="mt-1 font-semibold">{{ $task->assignee?->name ?? 'Waiting for ITD assignment' }}</dd>
                </div>
                <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Needed by</dt>
                    <dd class="mt-1 font-semibold">{{ $task->due_date?->format('M j, Y') ?? 'No deadline' }}</dd>
                </div>
            </dl>
        </section>
    </div>
@endsection
