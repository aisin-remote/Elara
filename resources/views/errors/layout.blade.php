<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <link rel="icon" type="image/svg+xml" href="{{ asset('elara-favicon.svg') }}">
        <link rel="alternate icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('elara-icon-180.png') }}">
        <title>{{ $status }} · {{ $title }} · Orbitra</title>
        @vite(['resources/css/app.css'])
    </head>
    <body>
        <main class="grid min-h-screen place-items-center px-5 py-16">
            <section class="w-full max-w-xl rounded-[2rem] border border-slate-200 bg-white p-8 text-center shadow-xl sm:p-12 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="error-title">
                <x-logo class="justify-center" />
                <p class="mt-10 text-sm font-black uppercase tracking-[0.2em] text-orbit-600">Error {{ $status }}</p>
                <h1 id="error-title" class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ $title }}</h1>
                <p class="mt-4 leading-7 text-slate-500 dark:text-slate-400">{{ $description }}</p>
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <x-link-button href="{{ url()->previous() === url()->current() ? route('home') : url()->previous() }}" variant="secondary">Go back</x-link-button>
                    <x-link-button href="{{ auth()->check() ? route('app.dashboard') : route('home') }}">{{ auth()->check() ? 'Open dashboard' : 'Return home' }}</x-link-button>
                </div>
            </section>
        </main>
    </body>
</html>
