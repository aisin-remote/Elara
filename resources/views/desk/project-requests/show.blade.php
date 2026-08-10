@extends('layouts.requester')

@section('title', $request->title)
@section('page-title', 'Proposal')

@section('content')
    <a href="{{ route('desk.index') }}" class="text-sm font-semibold text-orbit-700 dark:text-orbit-300">← All requests</a>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-6 dark:border-slate-800">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Usulan proyek</p>
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
        'monitoringUrl' => route('internal.project-requests.monitoring', $request),
    ])

    @can('departmentDecide', $request)
        @include('desk.approvals._decision-form')
    @endcan

    @if ($request->meeting && ! $request->meetingHeld())
        <p class="mt-4 rounded-xl bg-orbit-50 p-4 text-sm text-orbit-800 dark:bg-orbit-950/50 dark:text-orbit-200">
            Rapat scoping dijadwalkan {{ $request->meeting->start_at->format('l, j M') }} pukul {{ $request->meeting->start_at->format('H:i') }}.
            @if ($request->meeting->meeting_url)
                <a href="{{ $request->meeting->meeting_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold underline">Buka tautan rapat</a>
            @endif
        </p>
    @endif

    @if ($request->status === App\Enums\ProjectRequestStatus::NEEDS_INFO)
        <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/60 dark:bg-amber-950/30">
            <h2 class="font-bold text-amber-900 dark:text-amber-200">Perlu penjelasan tambahan</h2>
            <p class="mt-2 text-sm text-amber-800 dark:text-amber-300">{{ $request->needs_info_stage === 'department' ? $request->department_decision_note : ($request->manager_note ?: $request->spv_note) }}</p>

            @can('resubmit', $request)
                <form method="POST" action="{{ route('desk.project-requests.resubmit', $request) }}" class="mt-5 space-y-4">
                    @csrf
                    @foreach ([['benefit', 'What the business gains'], ['concept', 'What it is'], ['business_process', 'The process it supports'], ['flow', 'How it runs end to end']] as [$field, $label])
                        <div>
                            <x-label for="{{ $field }}">{{ $label }}</x-label>
                            <x-textarea id="{{ $field }}" name="{{ $field }}" rows="4" required>{{ old($field, $request->{$field}) }}</x-textarea>
                            <x-field-error :name="$field" />
                        </div>
                    @endforeach
                    <x-button>Kirim ulang untuk ditinjau</x-button>
                </form>
            @endcan
        </section>
    @elseif ($request->status === App\Enums\ProjectRequestStatus::REJECTED)
        <x-alert variant="error" :dismissible="false" class="mt-6 max-w-none" title="Tidak disetujui">
            {{ $request->manager_note ?: $request->spv_note ?: $request->department_decision_note ?: 'No reason was recorded.' }}
        </x-alert>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_300px] lg:items-start">
        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            @foreach ([['Benefit', $request->benefit], ['Concept', $request->concept], ['Business process', $request->business_process], ['Flow', $request->flow]] as $index => [$label, $value])
                <div @class(['border-t border-slate-100 pt-5 dark:border-slate-800' => $index > 0])>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</h2>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $value }}</p>
                </div>
            @endforeach
        </section>

        <div class="space-y-6">
            @include('app.approvals._timeline', ['timeline' => $timeline])

            @can('withdraw', $request)
                <form method="POST" action="{{ route('desk.project-requests.withdraw', $request) }}" onsubmit="return confirm('Withdraw this proposal?')">
                    @csrf
                    <x-button variant="secondary" class="w-full">Tarik usulan</x-button>
                </form>
            @endcan
        </div>
    </div>

    <x-attachments class="mt-6" locale="id"
        :files="$request->attachments"
        :can-upload="$request->requester_id === auth()->id() && $request->status->isOpen()"
        :upload-url="route('internal.project-requests.attachments.store', $request)" />
@endsection
