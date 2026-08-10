@extends('layouts.app')

@section('title', 'Profile settings')
@section('page-title', 'Settings')

@section('content')
    <div>
        @include('app.settings._navigation')

        <form method="POST" action="{{ route('internal.settings.profile.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[280px_1fr]">
            @csrf @method('PATCH')
            <aside class="h-fit rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
                @if (auth()->user()->avatar_path)
                    <img src="{{ route('internal.users.avatar', auth()->user()) }}" alt="{{ auth()->user()->name }}" class="mx-auto size-28 rounded-3xl object-cover ring-4 ring-orbit-50 dark:ring-orbit-950">
                @else
                    <div class="mx-auto grid size-28 place-items-center rounded-3xl bg-gradient-to-br from-orbit-400 to-indigo-500 text-3xl font-bold text-white">{{ str(auth()->user()->first_name)->substr(0, 1) }}{{ str(auth()->user()->last_name)->substr(0, 1) }}</div>
                @endif
                <h2 class="mt-4 text-lg font-bold">{{ auth()->user()->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ auth()->user()->job_title ?: 'Add your role' }}</p>
                <label for="avatar" class="mt-5 block cursor-pointer rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold hover:border-orbit-400 hover:text-orbit-700 dark:border-slate-700">
                    Upload photo
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
                </label>
                <x-field-error name="avatar" />
                @if (auth()->user()->avatar_path)
                    <label class="mt-3 flex items-center justify-center gap-2 text-sm text-slate-500"><input type="checkbox" name="remove_avatar" value="1" class="rounded border-slate-300 text-orbit-600"> Remove current photo</label>
                @endif
            </aside>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                <div><p class="text-sm font-semibold text-orbit-600">Personal details</p><h2 class="mt-1 text-xl font-bold">Your Orbitra profile</h2><p class="mt-2 text-sm text-slate-500">This information is visible to teammates in shared workspaces.</p></div>
                <div class="mt-7 grid gap-5 sm:grid-cols-2 2xl:grid-cols-3">
                    <div><x-label for="first_name">First name</x-label><x-input id="first_name" name="first_name" value="{{ old('first_name', auth()->user()->first_name) }}" required /><x-field-error name="first_name" /></div>
                    <div><x-label for="last_name">Last name</x-label><x-input id="last_name" name="last_name" value="{{ old('last_name', auth()->user()->last_name) }}" required /><x-field-error name="last_name" /></div>
                    <div class="sm:col-span-2 2xl:col-span-3"><x-label for="email">Email address</x-label><x-input id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" :readonly="auth()->user()->isOrganizationManaged()" required /><x-field-error name="email" />@if(auth()->user()->isOrganizationManaged())<p class="mt-2 text-xs text-slate-500">Managed by your company directory.</p>@endif</div>
                    @unless (auth()->user()->isOrganizationManaged())
                        <div class="sm:col-span-2 2xl:col-span-3 rounded-2xl bg-amber-50 p-4 dark:bg-amber-950/30"><x-label for="current_password">Current password <span class="font-normal text-slate-500">(required only when changing email)</span></x-label><x-input id="current_password" name="current_password" type="password" autocomplete="current-password" /><x-field-error name="current_password" /></div>
                    @endunless
                    <div><x-label for="phone">Phone</x-label><x-input id="phone" name="phone" value="{{ old('phone', auth()->user()->phone) }}" /></div>
                    <div><x-label for="job_title">Job title</x-label><x-input id="job_title" name="job_title" value="{{ old('job_title', auth()->user()->job_title) }}" /></div>
                    <div class="sm:col-span-2 2xl:col-span-3"><x-label for="company">Company</x-label><x-input id="company" name="company" value="{{ old('company', auth()->user()->company) }}" /></div>
                    <div class="sm:col-span-2 2xl:col-span-3"><x-label for="bio">Bio</x-label><x-textarea id="bio" name="bio" rows="4">{{ old('bio', auth()->user()->bio) }}</x-textarea><x-field-error name="bio" /></div>
                    <div><x-label for="locale">Language</x-label><x-select id="locale" name="locale"><option value="en" @selected(old('locale', auth()->user()->locale) === 'en')>English</option><option value="id" @selected(old('locale', auth()->user()->locale) === 'id')>Bahasa Indonesia</option></x-select></div>
                    <div><x-label for="timezone">Timezone</x-label><x-select id="timezone" name="timezone">@foreach ($timezones as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', auth()->user()->timezone) === $timezone)>{{ str_replace('_', ' ', $timezone) }}</option>@endforeach</x-select></div>
                    <div><x-label for="profile_theme">Theme</x-label><x-select id="profile_theme" name="theme"><option value="light" @selected(old('theme', auth()->user()->theme) === 'light')>Light</option><option value="dark" @selected(old('theme', auth()->user()->theme) !== 'light')>Dark</option></x-select></div>
                </div>
                <div class="mt-7 flex justify-end"><x-button>Save profile</x-button></div>
            </div>
        </form>
    </div>
@endsection
