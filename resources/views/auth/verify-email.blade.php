@extends('layouts.guest')

@section('title', 'Verify email')

@section('content')
    <section class="w-full max-w-md rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-2xl shadow-slate-900/5 backdrop-blur sm:p-8 dark:border-slate-800 dark:bg-slate-900/90" aria-labelledby="verify-title">
        <p class="text-sm font-semibold text-orbit-600 dark:text-orbit-400">One last step</p>
        <h1 id="verify-title" class="mt-2 text-3xl font-bold tracking-tight">Verify your email</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Open the link we sent to {{ auth()->user()->email }}. The link confirms that the address belongs to you.</p>
        <div class="mt-6"><x-status /></div>

        <form method="POST" action="{{ route('verification.send') }}" class="mt-6" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            <x-button class="w-full" x-bind:disabled="submitting">
                <span x-show="!submitting">Resend verification email</span>
                <span x-cloak x-show="submitting">Sending…</span>
            </x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <x-button type="submit" variant="ghost" class="w-full">Sign out</x-button>
        </form>
    </section>
@endsection
