@extends('layouts.app')

@section('title', 'Schedule')
@section('page-title', 'Schedule')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm text-slate-500">{{ $workspace->name }} / {{ $workspace->timezone }}</p><h2 class="mt-1 text-2xl font-bold tracking-tight">Weekly schedule</h2></div>
        @can('create', [App\Models\ScheduleEvent::class, $workspace])<x-button type="button" onclick="document.getElementById('schedule-create-dialog').showModal()"><x-icon name="plus"/>New event</x-button>@endcan
    </div>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900" data-schedule data-url="{{ $calendarUrl }}" data-timezone="{{ $workspace->timezone }}" data-week-start="{{ data_get($workspace->settings_json, 'week_start', 1) }}">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Week</p><h3 class="mt-1 text-lg font-bold" data-schedule-title>Schedule</h3></div>
            <div class="flex gap-2"><x-button type="button" variant="secondary" data-schedule-action="prev">←</x-button><x-button type="button" variant="secondary" data-schedule-action="today">Today</x-button><x-button type="button" variant="secondary" data-schedule-action="next">→</x-button></div>
        </div>
        <p class="mt-8 text-center text-sm text-slate-500" data-schedule-loading>Loading schedule…</p>
        <div class="mt-5 hidden max-h-[700px] overflow-auto rounded-xl border border-slate-200 md:block dark:border-slate-800" data-schedule-grid></div>
        <div class="mt-5 space-y-3 md:hidden" data-schedule-agenda></div>
    </section>

    @can('create', [App\Models\ScheduleEvent::class, $workspace])
        <dialog id="schedule-create-dialog" class="m-0 h-full max-h-none w-full max-w-none bg-transparent p-0 backdrop:bg-slate-950/60 sm:m-auto sm:h-auto sm:max-h-[90vh] sm:w-[620px] sm:rounded-2xl">
            <div class="h-full overflow-y-auto bg-white p-5 dark:bg-slate-900 sm:rounded-2xl sm:border sm:border-slate-200 sm:p-6 dark:sm:border-slate-800">
                <div class="flex items-center justify-between"><h3 class="text-xl font-bold">New schedule event</h3><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" onclick="this.closest('dialog').close()" aria-label="Close">✕</button></div>
                <form method="POST" action="{{ route('internal.schedule-events.store', $workspace) }}" class="mt-5 space-y-4">
                    @csrf
                    @include('app.schedule._form', ['prefix' => 'create'])
                    <div class="flex justify-end gap-2"><x-button type="button" variant="secondary" onclick="this.closest('dialog').close()">Cancel</x-button><x-button>Save event</x-button></div>
                </form>
            </div>
        </dialog>

        <dialog id="schedule-edit-dialog" class="m-0 h-full max-h-none w-full max-w-none bg-transparent p-0 backdrop:bg-slate-950/60 sm:m-auto sm:h-auto sm:max-h-[90vh] sm:w-[620px] sm:rounded-2xl">
            <div class="h-full overflow-y-auto bg-white p-5 dark:bg-slate-900 sm:rounded-2xl sm:border sm:border-slate-200 sm:p-6 dark:sm:border-slate-800">
                <div class="flex items-center justify-between"><h3 class="text-xl font-bold">Edit schedule event</h3><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" onclick="this.closest('dialog').close()" aria-label="Close">✕</button></div>
                <form method="POST" action="" class="mt-5 space-y-4" data-schedule-edit-form>
                    @csrf @method('PATCH')
                    @include('app.schedule._form', ['prefix' => 'edit'])
                    <input type="hidden" name="version" value="1" data-schedule-field="version">
                    <div class="flex justify-end gap-2"><x-button type="button" variant="secondary" onclick="this.closest('dialog').close()">Cancel</x-button><x-button>Save changes</x-button></div>
                </form>
                <form method="POST" action="" class="mt-4 border-t border-slate-200 pt-4" data-schedule-delete-form onsubmit="return confirm('Delete this event?')">@csrf @method('DELETE')<x-button variant="danger">Delete event</x-button></form>
            </div>
        </dialog>
    @endcan
@endsection
