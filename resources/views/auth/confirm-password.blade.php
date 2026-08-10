@extends('layouts.guest')

@section('title', 'Confirm password')

@section('content')
    <section class="w-full max-w-md rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-2xl shadow-slate-900/5 backdrop-blur sm:p-8 dark:border-slate-800 dark:bg-slate-900/90" aria-labelledby="confirm-title">
        <p class="text-sm font-semibold text-orbit-600 dark:text-orbit-400">Security check</p>
        <h1 id="confirm-title" class="mt-2 text-3xl font-bold tracking-tight">Confirm your password</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Re-enter your password before continuing to a sensitive action.</p>
        <div class="mt-6"><x-auth-errors /></div>

        <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-5" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            <div>
                <x-label for="password" value="Password" />
                <x-input id="password" name="password" type="password" required autofocus autocomplete="current-password" />
            </div>
            <x-button class="w-full" x-bind:disabled="submitting">
                <span x-show="!submitting">Confirm password</span>
                <span x-cloak x-show="submitting">Confirming…</span>
            </x-button>
        </form>
    </section>
@endsection
