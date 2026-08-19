@extends('layouts.requester')

@section('title', 'New request')
@section('page-title', 'New request')

@section('content')
    <div class="border-b border-slate-200 pb-6 dark:border-slate-800">
        <h2 class="text-2xl font-bold tracking-tight">Request a change</h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
            Describe the current condition, the target condition, and the benefit. A supervisor will review it,
            then ITD will turn it into planned work. You do not need to design the solution.
        </p>
    </div>

    <x-auth-errors class="mt-6" />

    <div class="mt-6 flex flex-wrap gap-3">
        <x-link-button href="{{ asset('docs/elara-feature-request-guide.pdf') }}" variant="secondary" download>
            <x-icon name="download" />
            Download Guide
        </x-link-button>
        <x-link-button href="{{ asset('docs/elara-feature-request-guide-id.pdf') }}" variant="secondary" download>
            <x-icon name="download" />
            Unduh Panduan
        </x-link-button>
    </div>

    @if ($systems->isEmpty())
        <x-alert variant="info" :dismissible="false" class="mt-6 max-w-none">
            No systems are currently open for requests. Ask an administrator to add the system under Settings → Master data.
        </x-alert>
    @else
        <form method="POST" action="{{ route('desk.requests.store', $workspace) }}" class="mt-6 space-y-6">
            @csrf

            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <x-label for="system_public_id">Which system?</x-label>
                <x-searchable-select
                    id="system_public_id"
                    name="system_public_id"
                    :selected="old('system_public_id')"
                    placeholder="Select a system"
                    search-placeholder="Search systems…"
                    :options="$systems->map(fn ($system) => [
                        'value' => $system->public_id,
                        'label' => $system->name.(($queueDepth[$system->public_id] ?? 0) > 0
                            ? ' — '.$queueDepth[$system->public_id].' ahead of you'
                            : ''),
                    ])->values()"
                />
                <x-field-error name="system_public_id" />
                <p class="mt-2 text-xs text-slate-500">ITD assigns the PIC when the request is approved, based on the work and available capacity.</p>
            </section>

            <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <x-label for="title">Short title</x-label>
                    <x-input id="title" name="title" value="{{ old('title') }}" placeholder="Export the monthly stock report" required />
                    <x-field-error name="title" />
                </div>

                <div>
                    <x-label for="problem">Current condition</x-label>
                    <x-textarea id="problem" name="problem" rows="4" placeholder="We copy the figures into a spreadsheet every month. It takes two days and frequently introduces errors.">{{ old('problem') }}</x-textarea>
                    <x-field-error name="problem" />
                </div>

                <div>
                    <x-label for="desired_outcome">Target condition</x-label>
                    <x-textarea id="desired_outcome" name="desired_outcome" rows="4" placeholder="I can download the same report directly from the system with the columns we already use.">{{ old('desired_outcome') }}</x-textarea>
                    <x-field-error name="desired_outcome" />
                </div>

                <div>
                    <x-label for="benefit">Benefit</x-label>
                    <x-textarea id="benefit" name="benefit" rows="4" placeholder="Saves about two staff days each month and reduces transcription errors in the finance report.">{{ old('benefit') }}</x-textarea>
                    <x-field-error name="benefit" />
                </div>

                <div>
                    <x-label for="urgency">How urgent is it?</x-label>
                    <x-select id="urgency" name="urgency">
                        @foreach ($urgencies as $urgency)
                            <option value="{{ $urgency->value }}" @selected(old('urgency', 'normal') === $urgency->value)>{{ $urgency->label() }}</option>
                        @endforeach
                    </x-select>
                    <p class="mt-2 text-xs text-slate-500">Urgency helps reviewers prioritize the queue; it does not bypass capacity planning.</p>
                </div>
            </section>

            <div class="flex gap-3">
                <x-button>Submit request</x-button>
                <x-link-button href="{{ route('desk.index') }}" variant="secondary">Cancel</x-link-button>
            </div>
        </form>
    @endif
@endsection
