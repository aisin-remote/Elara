@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
    <section class="w-full max-w-md rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-2xl shadow-slate-900/5 backdrop-blur sm:p-8 dark:border-slate-800 dark:bg-slate-900/90" aria-labelledby="forgot-title">
        <p class="text-sm font-semibold text-orbit-600 dark:text-orbit-400">Account recovery</p>
        <h1 id="forgot-title" class="mt-2 text-3xl font-bold tracking-tight">Reset your password</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Enter your account email and we will send a secure reset link.</p>
        @if (config('organization.jit_auth'))
            <p class="mt-3 rounded-2xl bg-sky-50 px-4 py-3 text-sm leading-6 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200">Company accounts use the password from the corporate directory. Change or recover that password through your company account service.</p>
        @endif

        <div class="mt-6 space-y-4"><x-status /><x-auth-errors /></div>

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            <div>
                <x-label for="email" value="Email address" />
                <x-input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" />
            </div>
            <x-button class="w-full" x-bind:disabled="submitting">
                <span x-show="!submitting">Email reset link</span>
                <span x-cloak x-show="submitting">Sending…</span>
            </x-button>
        </form>

        <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="font-semibold text-orbit-700 hover:underline dark:text-orbit-300">Back to sign in</a></p>
    </section>
@endsection
