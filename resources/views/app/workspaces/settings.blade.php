@extends('layouts.app')

@section('title', 'Workspace settings')
@section('page-title', 'Workspace settings')

@section('content')
    <div>
        @include('app.settings._navigation')
        <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-bold">Workspace details</h2>
            <form method="POST" action="{{ route('internal.workspaces.update', $workspace) }}" class="mt-6 space-y-5">
            @csrf @method('PATCH')
            <div><x-label for="name">Name</x-label><x-input id="name" name="name" value="{{ old('name', $workspace->name) }}" required /><x-field-error name="name" /></div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div><x-label for="icon">Icon or emoji</x-label><x-input id="icon" name="icon" value="{{ old('icon', $workspace->icon) }}" maxlength="20" /><x-field-error name="icon" /></div>
                <div><x-label for="timezone">Timezone</x-label><x-input id="timezone" name="timezone" value="{{ old('timezone', $workspace->timezone) }}" required /><x-field-error name="timezone" /></div>
                <div><x-label for="locale">Locale</x-label><x-select id="locale" name="locale"><option value="en" @selected(old('locale', $workspace->locale) === 'en')>English</option><option value="id" @selected(old('locale', $workspace->locale) === 'id')>Bahasa Indonesia</option></x-select></div>
                <div><x-label for="week_start">Week starts on</x-label><x-select id="week_start" name="week_start"><option value="1" @selected(old('week_start', data_get($workspace->settings_json, 'week_start', 1)) == 1)>Monday</option><option value="0" @selected(old('week_start', data_get($workspace->settings_json, 'week_start', 1)) == 0)>Sunday</option></x-select></div>
            </div>
            <x-button>Save changes</x-button>
            </form>
        </div>
    </div>
@endsection
