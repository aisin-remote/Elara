@extends('layouts.requester')

@section('title', 'Department approvals')
@section('page-title', 'Approvals')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 pb-6 dark:border-slate-800">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-orbit-600">{{ $profile['department_code'] }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">Department approvals</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                Requests from {{ $profile['department_name'] }} that require department approval before going to ITD.
            </p>
        </div>
        <x-badge tone="slate">{{ $profile['rank_code'] }} · {{ $profile['rank_name'] }}</x-badge>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        @foreach ([['Feature requests', $features, false], ['Project proposals', $projects, true]] as [$title, $rows, $isProject])
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-bold">{{ $title }}</h3>
                    <span class="text-sm font-semibold tabular-nums text-slate-400">{{ $rows->count() }}</span>
                </div>

                @if ($rows->isEmpty())
                    <div class="mt-4">
                        <x-empty-state icon="check" title="Nothing waiting" description="New requests from your department will appear here." />
                    </div>
                @else
                    <div class="mt-4 space-y-3">
                        @foreach ($rows as $row)
                            <a href="{{ $isProject ? route('desk.project-requests.show', $row) : route('desk.requests.show', $row) }}"
                                class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-orbit-300 hover:bg-orbit-50/40 dark:border-slate-800 dark:hover:border-orbit-800 dark:hover:bg-orbit-950/20">
                                <x-avatar :name="$row->requester->name" size="size-10" />
                                <span class="min-w-0 flex-1">
                                    <strong class="block truncate text-sm">{{ $row->title }}</strong>
                                    <span class="mt-1 block truncate text-xs text-slate-500">
                                        {{ $row->requester->name }}{{ $isProject ? '' : ' · '.$row->system->name }}
                                    </span>
                                </span>
                                <span class="whitespace-nowrap text-xs text-slate-400">{{ $row->created_at->diffForHumans() }}</span>
                                <x-icon name="chevron-right" class="size-4 shrink-0 text-slate-400" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>
@endsection
