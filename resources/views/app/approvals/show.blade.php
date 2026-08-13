@extends('layouts.app')

@section('title', $request->title)
@section('page-title', 'Approvals')

@section('content')
    <a href="{{ route('app.approvals.index', $workspace) }}" class="text-sm font-semibold text-orbit-700 dark:text-orbit-300">← Approvals queue</a>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-6 dark:border-slate-800">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $request->system->name }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">{{ $request->title }}</h2>
            <p class="mt-2 text-sm text-slate-500">
                Raised by {{ $request->requester->name }} · {{ $request->created_at->format('M j, Y') }}
                @if ($pic = $request->system->pic()) · PIC {{ $pic->name }} @endif
            </p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            @if ($request->urgency->value === 'high')<x-badge tone="danger">Urgent</x-badge>@endif
            <x-badge :tone="$request->status->tone()">{{ $request->status->label() }}</x-badge>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_360px] xl:items-start">
        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Current condition</h3>
                <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $request->problem }}</p>
            </div>
            <div class="border-t border-slate-100 pt-5 dark:border-slate-800">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Target condition</h3>
                <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $request->desired_outcome }}</p>
            </div>
            <div class="border-t border-slate-100 pt-5 dark:border-slate-800">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Benefit</h3>
                <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $request->benefit }}</p>
            </div>
            @if ($request->decision_note)
                <div class="border-t border-slate-100 pt-5 dark:border-slate-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Last decision note</h3>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $request->decision_note }}</p>
                </div>
            @endif
        </section>

        <div class="space-y-6">
            @if ($canDecide && $request->status->isAwaitingReview())
                <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="mb-4 font-bold">Your decision</h3>
                    @include('app.approvals._decide-form', ['compact' => true])
                </section>
            @elseif (! $request->status->isAwaitingReview())
                <x-alert variant="info" :dismissible="false" class="max-w-none">
                    Already decided — {{ $request->status->label() }}{{ $request->reviewer ? ' by '.$request->reviewer->name : '' }}.
                </x-alert>
            @endif

            <x-attachments :files="$request->attachments" />

            @include('app.approvals._timeline')
        </div>
    </div>

    {{-- Outside the two-column grid on purpose: an editable task list in a 360px rail is
         unreadable, and widening the fields inside the rail only makes the rail wider. --}}
    @if (in_array($request->status->value, ['approved', 'scheduled', 'in_progress', 'delivered'], true))
        <div class="mt-6">
            @include('app.approvals._breakdown')
        </div>
    @endif
@endsection
