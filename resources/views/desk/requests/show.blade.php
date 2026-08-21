@extends('layouts.requester')

@section('title', $request->title)
@section('page-title', 'Request')

@section('content')
    <a href="{{ route('desk.index') }}" class="text-sm font-semibold text-orbit-700 dark:text-orbit-300">← All requests</a>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-6 dark:border-slate-800">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $request->system->name }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">{{ $request->title }}</h2>
            <p class="mt-2 text-sm text-slate-500">
                Submitted {{ $request->created_at->format('M j, Y') }} by {{ $request->requester->name }}
                @if ($request->requester_department_name)
                    · {{ $request->requester_department_name }} · {{ $request->requester_job_rank_code }}
                @endif
            </p>
        </div>
        <x-badge :tone="$request->status->tone()">{{ $request->status->label() }}</x-badge>
    </div>

    @include('desk._monitoring', [
        'monitoringUrl' => route('internal.requests.monitoring', $request),
    ])

    @can('departmentDecide', $request)
        @include('desk.approvals._decision-form')
    @endcan

    @if ($request->status === App\Enums\FeatureRequestStatus::NEEDS_INFO)
        <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/60 dark:bg-amber-950/30">
            <h2 class="font-bold text-amber-900 dark:text-amber-200">{{ $request->needs_info_stage === 'department' ? ($request->departmentReviewer?->name ?? 'Department approver') : ($request->reviewer?->name ?? 'A supervisor') }} needs more detail</h2>
            <p class="mt-2 text-sm text-amber-800 dark:text-amber-300">{{ $request->needs_info_stage === 'department' ? $request->department_decision_note : $request->decision_note }}</p>

            @can('resubmit', $request)
                <form method="POST" action="{{ route('desk.requests.resubmit', $request) }}" class="mt-5 space-y-4">
                    @csrf
                    <div><x-label for="problem">Current condition</x-label><x-textarea id="problem" name="problem" rows="4" required>{{ old('problem', $request->problem) }}</x-textarea><x-field-error name="problem" /></div>
                    <div><x-label for="desired_outcome">Target condition</x-label><x-textarea id="desired_outcome" name="desired_outcome" rows="4" required>{{ old('desired_outcome', $request->desired_outcome) }}</x-textarea><x-field-error name="desired_outcome" /></div>
                    <div><x-label for="benefit">Benefit</x-label><x-textarea id="benefit" name="benefit" rows="4" required>{{ old('benefit', $request->benefit) }}</x-textarea><x-field-error name="benefit" /></div>
                    <x-button>Resubmit for review</x-button>
                </form>
            @endcan
        </section>
    @elseif ($request->status === App\Enums\FeatureRequestStatus::REJECTED && ($request->decision_note || $request->department_decision_note))
        <x-alert variant="error" :dismissible="false" class="mt-6 max-w-none" title="Not approved">
            {{ $request->decision_note ?: $request->department_decision_note }}
        </x-alert>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_300px] lg:items-start">
        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Current condition</h2>
                <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $request->problem }}</p>
            </div>
            <div class="border-t border-slate-100 pt-5 dark:border-slate-800">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Target condition</h2>
                <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $request->desired_outcome }}</p>
            </div>
            <div class="border-t border-slate-100 pt-5 dark:border-slate-800">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Benefit</h2>
                <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $request->benefit }}</p>
            </div>
        </section>

        <div class="space-y-6">
            @include('app.approvals._timeline', ['timeline' => $timeline])

            @can('withdraw', $request)
                <form method="POST" action="{{ route('desk.requests.withdraw', $request) }}" onsubmit="return confirm('Withdraw this request?')">
                    @csrf
                    <x-button variant="secondary" class="w-full">Withdraw request</x-button>
                </form>
            @endcan
        </div>
    </div>

    <x-attachments class="mt-6"
        :files="$request->attachments"
        :can-upload="$request->requester_id === auth()->id() && $request->status->isOpen()"
        :upload-url="route('internal.requests.attachments.store', $request)" />
    <x-discussion :subject="$request" />
@endsection
