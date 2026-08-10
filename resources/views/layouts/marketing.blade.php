<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="themePreference">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="Orbitra keeps projects, tasks, schedules, files, and team communication in one focused workspace.">
        <title>@yield('title', 'Project management for focused teams') · Orbitra</title>
        <script>
            const orbitraTheme = localStorage.getItem('orbitra-theme') ?? 'system';
            document.documentElement.classList.toggle('dark', orbitraTheme === 'dark' || (orbitraTheme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches));
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="overflow-x-hidden bg-white dark:bg-slate-950">
        <a href="#main-content" class="sr-only z-[100] rounded-lg bg-white px-4 py-2 font-semibold text-slate-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Skip to content</a>
        <x-connectivity-status />

        <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/90">
            <div class="mx-auto flex min-h-20 max-w-7xl items-center justify-between gap-5 px-5 lg:px-8">
                <x-logo />
                <nav class="hidden items-center gap-7 text-sm font-semibold text-slate-600 lg:flex dark:text-slate-300" aria-label="Public navigation">
                    <a href="#features" class="hover:text-orbit-700 dark:hover:text-orbit-300">Features</a>
                    <a href="#product" class="hover:text-orbit-700 dark:hover:text-orbit-300">Product</a>
                    <a href="#integrations" class="hover:text-orbit-700 dark:hover:text-orbit-300">Integrations</a>
                    <a href="#access" class="hover:text-orbit-700 dark:hover:text-orbit-300">Access</a>
                    <a href="#resources" class="hover:text-orbit-700 dark:hover:text-orbit-300">Resources</a>
                </nav>
                <div class="hidden items-center gap-3 sm:flex">
                    <x-theme-toggle />
                    @auth
                        <x-link-button href="{{ route('app.dashboard') }}">Dashboard</x-link-button>
                    @else
                        <a href="{{ route('login') }}" class="px-2 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Log in</a>
                        <x-link-button href="{{ route(config('organization.jit_auth') ? 'login' : 'register') }}">Get started</x-link-button>
                    @endauth
                </div>
                <details class="relative shrink-0 sm:hidden">
                    <summary class="grid size-11 cursor-pointer list-none place-items-center rounded-xl border border-slate-200 text-xl dark:border-slate-700" aria-label="Open menu">☰</summary>
                    <nav class="absolute right-0 top-14 w-64 rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl dark:border-slate-700 dark:bg-slate-900" aria-label="Mobile public navigation">
                        @foreach ([['#features', 'Features'], ['#product', 'Product'], ['#integrations', 'Integrations'], ['#access', 'Access'], ['#resources', 'Resources']] as [$href, $label])
                            <a href="{{ $href }}" class="block rounded-xl px-3 py-3 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800">{{ $label }}</a>
                        @endforeach
                        <div class="mt-2 border-t border-slate-100 pt-2 dark:border-slate-800">
                            <a href="{{ auth()->check() ? route('app.dashboard') : route('login') }}" class="block rounded-xl px-3 py-3 text-sm font-semibold">{{ auth()->check() ? 'Dashboard' : 'Log in' }}</a>
                            @guest<a href="{{ route(config('organization.jit_auth') ? 'login' : 'register') }}" class="block rounded-xl bg-orbit-600 px-3 py-3 text-center text-sm font-semibold text-white">Get started</a>@endguest
                        </div>
                    </nav>
                </details>
            </div>
        </header>

        <main id="main-content">@yield('content')</main>

        <footer class="border-t border-slate-200 bg-slate-950 text-slate-300 dark:border-slate-800">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 sm:grid-cols-2 lg:grid-cols-5 lg:px-8">
                <div class="sm:col-span-2"><x-logo /><p class="mt-4 max-w-sm text-sm leading-6 text-slate-400">Original project-management software for teams that want clear ownership, calm collaboration, and measurable progress.</p></div>
                <div><h2 class="font-bold text-white">Product</h2><div class="mt-4 space-y-3 text-sm"><a class="block hover:text-white" href="#features">Features</a><a class="block hover:text-white" href="#integrations">Integrations</a><a class="block hover:text-white" href="#access">Unlimited access</a></div></div>
                <div><h2 class="font-bold text-white">Resources</h2><div class="mt-4 space-y-3 text-sm"><a class="block hover:text-white" href="#how-it-works">How it works</a><a class="block hover:text-white" href="#resources">FAQ</a>@auth<a class="block hover:text-white" href="{{ route('help') }}">Help center</a>@endauth</div></div>
                <div><h2 class="font-bold text-white">Legal</h2><div class="mt-4 space-y-3 text-sm"><a class="block hover:text-white" href="{{ route('legal.privacy') }}">Privacy</a><a class="block hover:text-white" href="{{ route('legal.terms') }}">Terms</a><a class="block hover:text-white" href="{{ route('legal.accessibility') }}">Accessibility</a>@foreach(array_filter(config('orbitra.social_links', [])) as $network => $url)<a class="block hover:text-white" href="{{ $url }}" rel="noopener noreferrer">{{ ucfirst($network) }}</a>@endforeach</div></div>
            </div>
            <div class="border-t border-slate-800 px-5 py-6 text-center text-xs text-slate-500">© {{ now()->year }} Orbitra. Built with an original identity and no copied third-party branding or assets.</div>
        </footer>
    </body>
</html>
