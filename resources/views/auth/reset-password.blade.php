@extends('layouts.guest')

@section('title', 'Choose password')

@section('content')
    <section class="w-full max-w-md rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-2xl shadow-slate-900/5 backdrop-blur sm:p-8 dark:border-slate-800 dark:bg-slate-900/90" aria-labelledby="reset-title">
        <p class="text-sm font-semibold text-orbit-600 dark:text-orbit-400">Account recovery</p>
        <h1 id="reset-title" class="mt-2 text-3xl font-bold tracking-tight">Choose a new password</h1>
        <div class="mt-6"><x-auth-errors /></div>

        <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-5" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <x-label for="email" value="Email address" />
                <x-input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autocomplete="username" />
            </div>
            <div>
                <x-label for="password" value="New password" />
                <x-input id="password" name="password" type="password" required autofocus autocomplete="new-password" />
            </div>
            <div>
                <x-label for="password_confirmation" value="Confirm new password" />
                <x-input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
            </div>
            <x-button class="w-full" x-bind:disabled="submitting">
                <span x-show="!submitting">Reset password</span>
                <span x-cloak x-show="submitting">Updating…</span>
            </x-button>
        </form>
    </section>
@endsection
