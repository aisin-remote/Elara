@extends('layouts.app')

@section('title', $request->title)
@section('page-title', 'Approvals')

@section('content')
    <a href="{{ route('app.approvals.index', $workspace) }}" class="text-sm font-semibold text-orbit-700 dark:text-orbit-300">← Approvals queue</a>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-6 dark:border-slate-800">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Project request</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">{{ $request->title }}</h2>
            <p class="mt-2 text-sm text-slate-500">
                Proposed by {{ $request->requester->name }} · {{ $request->created_at->format('M j, Y') }}
                @if ($request->target_date) · hoped for {{ $request->target_date->format('M j, Y') }} @endif
            </p>
        </div>
        <x-badge :tone="$request->status->tone()">{{ $request->status->label() }}</x-badge>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_380px] xl:items-start">
        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            @foreach ([['Benefit', $request->benefit], ['Concept', $request->concept], ['Business process', $request->business_process], ['Flow', $request->flow]] as $index => [$label, $value])
                <div @class(['border-t border-slate-100 pt-5 dark:border-slate-800' => $index > 0])>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</h3>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $value }}</p>
                </div>
            @endforeach

            @if ($request->meeting_note)
                <div class="border-t border-slate-100 pt-5 dark:border-slate-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">What came out of the scoping meeting</h3>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $request->meeting_note }}</p>
                    <p class="mt-2 text-xs text-slate-500">Recorded {{ $request->meeting_held_at->format('M j, Y') }}. The manager was probably not in the room — this is what they read.</p>
                </div>
            @endif
        </section>

        <div class="space-y-6">
            @if (! $request->meetingHeld())
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/60 dark:bg-amber-950/30">
                    <h3 class="font-bold text-amber-900 dark:text-amber-200">Scoping meeting first</h3>
                    <p class="mt-2 text-sm text-amber-800 dark:text-amber-300">
                        Nobody can sign until the meeting has happened and its outcome is recorded.
                    </p>

                    @if ($canRunMeeting)
                        @if (! $request->meeting)
                            <form method="POST" action="{{ route('app.approvals.projects.meeting', [$workspace, $request]) }}" class="mt-4 space-y-3">
                                @csrf
                                <div><x-label for="start_at">Starts</x-label><x-input id="start_at" type="datetime-local" name="start_at" required /><x-field-error name="start_at" /></div>
                                <div><x-label for="end_at">Ends</x-label><x-input id="end_at" type="datetime-local" name="end_at" required /><x-field-error name="end_at" /></div>
                                <div><x-label for="meeting_url">Meeting link</x-label><x-input id="meeting_url" type="url" name="meeting_url" placeholder="https://…" /><x-field-error name="meeting_url" /></div>
                                <x-button class="w-full">Book the meeting</x-button>
                            </form>
                        @else
                            <p class="mt-3 text-sm text-amber-800 dark:text-amber-300">
                                Booked for {{ $request->meeting->start_at->format('l, M j H:i') }} with {{ $request->meeting->attendees->count() }} attendees.
                            </p>
                            <form method="POST" action="{{ route('app.approvals.projects.meeting-held', [$workspace, $request]) }}" class="mt-4 space-y-3">
                                @csrf
                                <div>
                                    <x-label for="meeting_note">What came out of it?</x-label>
                                    <x-textarea id="meeting_note" name="meeting_note" rows="5" placeholder="Scope agreed, the ERP write-back is out of scope for phase one, procurement will own the supplier list."></x-textarea>
                                    <x-field-error name="meeting_note" />
                                </div>
                                <x-button class="w-full">Record the meeting</x-button>
                            </form>
                        @endif
                    @endif
                </section>
            @endif

            @if ($canSignSpv || $canSignManager)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" x-data="{ decision: 'approve' }">
                    <h3 class="font-bold">{{ $canSignManager ? 'Second signature' : 'First signature' }}</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $canSignManager ? 'The supervisor has already signed. Approving creates the project.' : 'Approving passes it to a manager for the second signature.' }}
                    </p>

                    <form method="POST" action="{{ route('app.approvals.projects.decide', [$workspace, $request]) }}" class="mt-4 space-y-4">
                        @csrf
                        <x-form-errors :except="['estimated_hours', 'note', 'decision']" />
                        <div class="space-y-2">
                            @foreach ([['approve', 'Approve'], ['needs_info', 'Ask for more detail'], ['reject', 'Reject']] as [$value, $label])
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-semibold dark:border-slate-800">
                                    <input type="radio" name="decision" value="{{ $value }}" x-model="decision" @checked($value === 'approve') class="border-slate-300 text-orbit-600 focus:ring-orbit-500">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @if ($canSignManager)
                            <div x-show="decision === 'approve'" x-cloak>
                                <x-label for="estimated_hours">Rough effort, in hours</x-label>
                                <x-input id="estimated_hours" type="number" step="1" min="1" name="estimated_hours" value="{{ old('estimated_hours') }}" placeholder="160" />
                                <x-field-error name="estimated_hours" />
                                <p class="mt-2 text-xs text-slate-500">Used to find a real slot against the team's capacity.</p>
                            </div>
                        @endif

                        <div x-show="decision !== 'approve'" x-cloak>
                            <x-label for="note">Why?</x-label>
                            <x-textarea id="note" name="note" rows="4">{{ old('note') }}</x-textarea>
                            <x-field-error name="note" />
                        </div>
                        <x-button class="w-full">Record decision</x-button>
                    </form>
                </section>
            @elseif ($request->status === App\Enums\ProjectRequestStatus::PENDING_MANAGER && $request->spv_id === auth()->id())
                <x-alert variant="info" :dismissible="false" class="max-w-none" title="Waiting on someone else">
                    You gave the first signature, so the second has to come from a different person — even
                    if you also hold the manager role.
                </x-alert>
            @endif

            @if ($request->project)
                <x-alert variant="success" :dismissible="false" class="max-w-none" title="Project created">
                    <a href="{{ route('app.projects.show', $request->project) }}" class="font-semibold underline">{{ $request->project->name }}</a>
                    is now in the Projects menu.
                </x-alert>
            @endif

            <x-attachments :files="$request->attachments" />

            @include('app.approvals._timeline')
        </div>
    </div>

    {{-- Full width, outside the grid: see the note in show.blade.php. --}}
    @if (in_array($request->status->value, ['approved', 'scheduled', 'in_progress', 'delivered'], true))
        <div class="mt-6">
            @include('app.approvals._breakdown')
        </div>
    @endif
@endsection
