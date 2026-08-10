@extends('layouts.app')

@section('title', 'Create workspace')
@section('page-title', 'Create workspace')

@section('content')
    <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-2xl font-bold">Give your team a home</h2>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">You will become the owner and can invite people after creation.</p>
        <form method="POST" action="{{ route('internal.workspaces.store') }}" class="mt-7 space-y-5">
            @csrf
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <div><x-label for="name">Workspace name</x-label><x-input id="name" name="name" value="{{ old('name') }}" required autofocus /><x-field-error name="name" /></div>
                <div><x-label for="timezone">Timezone</x-label><x-input id="timezone" name="timezone" value="{{ old('timezone', 'Asia/Jakarta') }}" required /><x-field-error name="timezone" /></div>
                <div><x-label for="locale">Locale</x-label><x-select id="locale" name="locale"><option value="en" @selected(old('locale') === 'en')>English</option><option value="id" @selected(old('locale', 'id') === 'id')>Bahasa Indonesia</option></x-select><x-field-error name="locale" /></div>
                <div><x-label for="week_start">Week starts on</x-label><x-select id="week_start" name="week_start"><option value="1">Monday</option><option value="0">Sunday</option></x-select><x-field-error name="week_start" /></div>
            </div>
            <x-button>Create workspace</x-button>
        </form>
    </div>
@endsection
