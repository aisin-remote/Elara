@extends('layouts.requester')

@section('title', $request->title)
@section('page-title', 'Project proposal')

@section('content')
    <a href="{{ route('desk.index') }}" class="text-sm font-semibold text-orbit-700 dark:text-orbit-300">← All requests</a>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-6 dark:border-slate-800">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Project proposal</p>
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

    @include('desk._monitoring', ['monitoringUrl' => route('internal.project-requests.monitoring', $request)])

    @can('departmentDecide', $request)
        @include('desk.approvals._decision-form')
    @endcan

    @if ($request->meeting && ! $request->meetingHeld())
        <p class="mt-4 rounded-xl bg-orbit-50 p-4 text-sm text-orbit-800 dark:bg-orbit-950/50 dark:text-orbit-200">
            The scoping meeting is scheduled for {{ $request->meeting->start_at->format('l, M j') }} at {{ $request->meeting->start_at->format('H:i') }}.
            @if ($request->meeting->meeting_url)
                <a href="{{ $request->meeting->meeting_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold underline">Open meeting link</a>
            @endif
        </p>
    @endif

    @if ($request->status === App\Enums\ProjectRequestStatus::NEEDS_INFO)
        <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/60 dark:bg-amber-950/30">
            <h2 class="font-bold text-amber-900 dark:text-amber-200">More information required</h2>
            <p class="mt-2 text-sm text-amber-800 dark:text-amber-300">{{ $request->needs_info_stage === 'department' ? $request->department_decision_note : ($request->manager_note ?: $request->spv_note) }}</p>

            @can('resubmit', $request)
                <form method="POST" action="{{ route('desk.project-requests.resubmit', $request) }}" class="mt-5 space-y-6">
                    @csrf
                    @include('desk.project-requests._form-fields', ['projectRequest' => $request])
                    <x-button>Resubmit for review</x-button>
                </form>
            @endcan
        </section>
    @elseif ($request->status === App\Enums\ProjectRequestStatus::REJECTED)
        <x-alert variant="error" :dismissible="false" class="mt-6 max-w-none" title="Not approved">
            {{ $request->manager_note ?: $request->spv_note ?: $request->department_decision_note ?: 'No reason was recorded.' }}
        </x-alert>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px] xl:items-start">
        @include('desk.project-requests._brief')

        <div class="space-y-6">
            @include('app.approvals._timeline', ['timeline' => $timeline])
            @can('withdraw', $request)
                <form method="POST" action="{{ route('desk.project-requests.withdraw', $request) }}" onsubmit="return confirm('Withdraw this proposal?')">
                    @csrf
                    <x-button variant="secondary" class="w-full">Withdraw proposal</x-button>
                </form>
            @endcan
        </div>
    </div>

    <x-attachments class="mt-6"
        :files="$request->attachments"
        :can-upload="$request->requester_id === auth()->id() && $request->status->isOpen()"
        :upload-url="route('internal.project-requests.attachments.store', $request)" />
    <x-discussion :subject="$request" />
@endsection
