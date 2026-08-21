@extends('layouts.requester')

@section('title', 'Schedule')
@section('page-title', 'Schedule')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500">{{ $workspace->name }} · {{ $deliveryWorkspace->timezone }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">Meet with the IT team</h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Invite the relevant IT members and keep your coordination meetings in the same delivery schedule.</p>
        </div>
        @if ($members->isNotEmpty())
            <x-button type="button" onclick="document.getElementById('requester-schedule-create-dialog').showModal()">
                <x-icon name="plus" />New meeting
            </x-button>
        @endif
    </div>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
        data-schedule data-schedule-dialog="requester-schedule-detail-dialog" data-url="{{ $calendarUrl }}" data-timezone="{{ $deliveryWorkspace->timezone }}" data-week-start="{{ data_get($deliveryWorkspace->settings_json, 'week_start', 1) }}">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Your meeting agenda</p>
                <h3 class="mt-1 text-lg font-bold" data-schedule-title>Schedule</h3>
            </div>
            <div class="flex gap-2">
                <x-button type="button" variant="secondary" data-schedule-action="prev" aria-label="Previous week">←</x-button>
                <x-button type="button" variant="secondary" data-schedule-action="today">Today</x-button>
                <x-button type="button" variant="secondary" data-schedule-action="next" aria-label="Next week">→</x-button>
            </div>
        </div>
        <p class="mt-8 text-center text-sm text-slate-500" data-schedule-loading>Loading schedule…</p>
        <div class="mt-5 hidden max-h-[700px] overflow-auto rounded-xl border border-slate-200 md:block dark:border-slate-800" data-schedule-grid></div>
        <div class="mt-5 space-y-3 md:hidden" data-schedule-agenda></div>
    </section>

    <dialog id="requester-schedule-detail-dialog" class="m-auto w-[min(92vw,620px)] max-w-none rounded-2xl bg-transparent p-0 backdrop:bg-slate-950/60">
        <div class="max-h-[90vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-orbit-600">Meeting details</p><h3 class="mt-1 text-xl font-bold" data-schedule-detail-title>Meeting</h3></div>
                <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" onclick="this.closest('dialog').close()" aria-label="Close"><x-icon name="close" /></button>
            </div>
            <p class="mt-4 text-sm font-semibold text-slate-600 dark:text-slate-300" data-schedule-detail-when></p>
            <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-500" data-schedule-detail-description></p>
            <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5 dark:border-slate-800">
                <a href="#" target="_blank" rel="noopener" data-schedule-meeting-link class="hidden inline-flex items-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">Join meeting</a>
                <a href="#" data-schedule-mom class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-violet-700">Create MOM</a>
            </div>
        </div>
    </dialog>

    @if ($members->isEmpty())
        <x-empty-state class="mt-6" title="No IT members are available" description="Ask an administrator to add active members to the ITD workspace before scheduling a meeting." icon="team" />
    @else
        <dialog id="requester-schedule-create-dialog" x-data x-init="@js($errors->any()) && $el.showModal()"
            class="m-0 h-full max-h-none w-full max-w-none bg-transparent p-0 backdrop:bg-slate-950/60 sm:m-auto sm:h-auto sm:max-h-[90vh] sm:w-[620px] sm:rounded-2xl">
            <div class="h-full overflow-y-auto bg-white p-5 dark:bg-slate-900 sm:rounded-2xl sm:border sm:border-slate-200 sm:p-6 dark:sm:border-slate-800">
                <div class="flex items-center justify-between gap-4">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-orbit-600">IT coordination</p><h3 class="mt-1 text-xl font-bold">New meeting</h3></div>
                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" onclick="this.closest('dialog').close()" aria-label="Close"><x-icon name="close" /></button>
                </div>

                <form method="POST" action="{{ route('desk.schedule.store', $workspace) }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <x-label for="requester-meeting-title">Title</x-label>
                        <x-input id="requester-meeting-title" name="title" value="{{ old('title') }}" required />
                        <x-field-error name="title" />
                    </div>
                    <div>
                        <x-label for="requester-meeting-description">Agenda or notes</x-label>
                        <x-textarea id="requester-meeting-description" name="description" rows="3">{{ old('description') }}</x-textarea>
                        <x-field-error name="description" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><x-label for="requester-meeting-start">Start</x-label><x-input id="requester-meeting-start" type="datetime-local" name="start_at" value="{{ old('start_at') }}" required /><x-field-error name="start_at" /></div>
                        <div><x-label for="requester-meeting-end">End</x-label><x-input id="requester-meeting-end" type="datetime-local" name="end_at" value="{{ old('end_at') }}" required /><x-field-error name="end_at" /></div>
                    </div>
                    <div>
                        <x-label for="requester-meeting-url">Meeting link</x-label>
                        <x-input id="requester-meeting-url" type="url" name="meeting_url" value="{{ old('meeting_url') }}" placeholder="https://meet.example.com/…" />
                        <x-field-error name="meeting_url" />
                    </div>
                    <fieldset>
                        <legend class="text-sm font-semibold text-slate-700 dark:text-slate-300">Invite IT members</legend>
                        <p class="mt-1 text-xs text-slate-500">Choose at least one person who should attend.</p>
                        <div class="mt-3 grid max-h-48 gap-2 overflow-y-auto rounded-xl border border-slate-200 p-3 sm:grid-cols-2 dark:border-slate-700">
                            @foreach ($members as $membership)
                                <label class="flex items-center gap-3 rounded-lg p-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                                    <input type="checkbox" name="attendee_public_ids[]" value="{{ $membership->user->public_id }}" @checked(in_array($membership->user->public_id, old('attendee_public_ids', []), true)) class="rounded border-slate-300 text-orbit-600">
                                    <x-avatar :src="filled($membership->user->avatar_path) ? route('internal.users.avatar', $membership->user) : null" :name="$membership->user->name" size="size-8" />
                                    <span class="min-w-0"><strong class="block truncate">{{ $membership->user->name }}</strong><small class="text-slate-500">{{ $membership->role->label() }}</small></span>
                                </label>
                            @endforeach
                        </div>
                        <x-field-error name="attendee_public_ids" />
                        <x-field-error name="attendee_public_ids.0" />
                    </fieldset>
                    <div class="flex justify-end gap-2">
                        <x-button type="button" variant="secondary" onclick="this.closest('dialog').close()">Cancel</x-button>
                        <x-button>Send invitation</x-button>
                    </div>
                </form>
            </div>
        </dialog>
    @endif
@endsection
