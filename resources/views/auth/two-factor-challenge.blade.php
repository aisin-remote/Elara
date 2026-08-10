@extends('layouts.guest')

@section('title', 'Two-factor challenge')

@section('content')
    <section class="w-full max-w-md rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-2xl shadow-slate-900/5 backdrop-blur sm:p-8 dark:border-slate-800 dark:bg-slate-900/90" aria-labelledby="two-factor-title">
        <p class="text-sm font-semibold text-orbit-600 dark:text-orbit-400">Security check</p>
        <h1 id="two-factor-title" class="mt-2 text-3xl font-bold tracking-tight">Enter your authentication code</h1>
        <p class="mt-2 text-sm text-slate-500">Use the six-digit code from your authenticator app or one unused recovery code.</p>
        <div class="mt-5"><x-auth-errors /></div>
        <form method="POST" action="{{ route('two-factor.login') }}" class="mt-6 space-y-5" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            <div><x-label for="code">Authentication or recovery code</x-label><x-input id="code" name="code" autocomplete="one-time-code" autofocus required /><x-field-error name="code" /></div>
            <x-button class="w-full" x-bind:disabled="submitting"><span x-show="!submitting">Verify and continue</span><span x-cloak x-show="submitting">Verifying…</span></x-button>
        </form>
    </section>
@endsection
