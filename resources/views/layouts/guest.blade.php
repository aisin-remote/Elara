<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="themePreference">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('elara-favicon.svg') }}">
        <title>@yield('title', 'Orbitra') · Orbitra</title>
        <script>
            // Dark by default before sign-in, but only when the visitor has expressed no
            // preference — someone who chose light and signed out still gets light back.
            // Set in the head so the first paint is already correct, with no flash.
            const orbitraTheme = localStorage.getItem('orbitra-theme') ?? 'dark';
            document.documentElement.classList.toggle('dark', orbitraTheme !== 'light');
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <a href="#main-content" class="sr-only z-[100] rounded-lg bg-white px-4 py-2 font-semibold text-slate-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Skip to content</a>
        <x-connectivity-status />
        {{-- A page that owns its whole shell (the split sign-in) opts out here rather than
             fighting the centred container. Everything else keeps the shared header. --}}
        @hasSection('bare')
            @yield('bare')
        @else
        <div class="relative min-h-screen overflow-hidden">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(46,176,251,0.16),_transparent_34%),radial-gradient(circle_at_bottom_right,_rgba(79,70,229,0.12),_transparent_30%)]"></div>
            <header class="relative mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
                <x-logo />
                <x-theme-toggle />
            </header>

            <main id="main-content" class="relative mx-auto grid min-h-[calc(100vh-96px)] max-w-7xl place-items-center px-6 py-10 lg:px-8">
                @yield('content')
            </main>
        </div>
        @endif
        <x-toast />
    </body>
</html>
