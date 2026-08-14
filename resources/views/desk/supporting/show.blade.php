@extends('layouts.requester')

@section('title', $task->title)
@section('page-title', 'Supporting')

@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <div><a href="{{ route('desk.index', ['tab'=>'supporting']) }}" class="text-sm font-semibold text-orbit-600">← Back to requests</a><h2 class="mt-3 text-2xl font-bold">{{ $task->title }}</h2><p class="mt-2 text-sm text-slate-500">Submitted {{ $task->created_at->diffForHumans() }}</p></div>
    <section class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"><div class="flex flex-wrap gap-2"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $task->status->badgeClasses() }}">{{ $task->status->label() }}</span><x-badge tone="slate">{{ $task->category->label() }}</x-badge><x-badge tone="slate">{{ $task->priority->label() }}</x-badge></div><h3 class="mt-6 text-sm font-bold uppercase tracking-wide text-slate-400">Details</h3><p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $task->description }}</p><div class="mt-6 grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-2 dark:border-slate-800"><div><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Assigned to</p><p class="mt-1 font-semibold">{{ $task->assignee?->name ?? 'Waiting for ITD assignment' }}</p></div><div><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Needed by</p><p class="mt-1 font-semibold">{{ $task->due_date?->format('M j, Y') ?? 'No deadline' }}</p></div></div></section>
</div>
@endsection
