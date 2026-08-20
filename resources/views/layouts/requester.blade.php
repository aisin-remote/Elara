<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="themePreference" data-theme="{{ auth()->user()->theme }}" data-theme-endpoint="{{ route('internal.settings.theme.update') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('elara-favicon.svg') }}">
        <link rel="alternate icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('elara-icon-180.png') }}">
        {{-- The requester and delivery desks use the same English product language. --}}
        <title>@yield('title', 'Requests') · Orbitra</title>
        <script>
            const orbitraTheme = localStorage.getItem('orbitra-theme') ?? @json(auth()->user()->theme);
            document.documentElement.classList.toggle('dark', orbitraTheme === 'dark' || (orbitraTheme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches));
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    {{-- The same shell as the delivery desk: same sidebar width, same appShell behaviour, same
         header. Two products that look unrelated make people think they are unrelated, and a
         requester who occasionally sees a colleague's screen should recognise the place. --}}
    <body data-user-name="{{ auth()->user()->name }}" x-data="appShell">
        <a href="#main-content" class="sr-only z-[100] rounded-lg bg-white px-4 py-2 font-semibold text-slate-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Skip to content</a>
        <x-connectivity-status />

        @php
            $waitingOnMe = App\Models\ValidationCheckpoint::visibleTo(auth()->user())->open()->count();
            $deskWorkspace = $activeWorkspace ?? null;
        @endphp

        <div class="min-h-screen bg-[#f7f8fb] lg:grid lg:grid-cols-[248px_1fr] dark:bg-slate-950">
            <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" x-on:click="closeSidebar()" aria-hidden="true"></div>

            <aside x-ref="sidebar" x-bind:inert="mobile && ! sidebarOpen ? true : null" x-on:keydown.tab="trapTab($event)" x-on:keydown.escape.window="closeSidebar()" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 z-50 flex w-[248px] flex-col overflow-hidden border-r border-slate-200 bg-white p-4 transition-transform dark:border-slate-800 dark:bg-slate-900 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0" aria-label="Request navigation">
                <div class="scrollbar-none min-h-0 flex-1 overflow-y-auto">
                    <div class="flex items-center justify-between">
                        <x-logo />
                        <button x-ref="sidebarClose" type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-800" x-on:click="closeSidebar()" aria-label="Close navigation">✕</button>
                    </div>

                    @if ($deskWorkspace)
                        <div class="mt-7">
                            <p class="px-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Workspace</p>
                            <p class="mt-2 truncate rounded-xl bg-slate-50 px-3 py-2.5 text-sm font-semibold dark:bg-slate-800/60">{{ $deskWorkspace->name }}</p>
                        </div>
                    @endif

                    <nav class="mt-7 space-y-1" aria-label="Main navigation">
                        @php($items = [
                            ['desk.it-timeline', 'Timeline', 'calendar', null],
                            ['desk.index', 'My requests', 'list', null],
                            ['desk.validations.index', 'Waiting on me', 'hourglass', $waitingOnMe],
                        ])
                        @foreach ($items as [$routeName, $label, $icon, $badge])
                            @php($isCurrent = request()->routeIs($routeName)
                                || ($routeName === 'desk.index' && request()->routeIs('desk.requests.*', 'desk.project-requests.*')))
                            <a href="{{ route($routeName) }}" @if ($isCurrent) aria-current="page" @endif class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ $isCurrent ? 'bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                                <x-icon :name="$icon" />{{ $label }}
                                @if ($badge)
                                    <span class="ml-auto min-w-5 rounded-full bg-rose-500 px-1.5 text-center text-[11px] font-bold leading-5 text-white">{{ $badge }}</span>
                                @endif
                            </a>
                        @endforeach

                        @if (($canApproveDepartmentRequests ?? false) && $deskWorkspace)
                            <a href="{{ route('desk.department-approvals.index', $deskWorkspace) }}" @if(request()->routeIs('desk.department-approvals.*')) aria-current="page" @endif
                                class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('desk.department-approvals.*') ? 'bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                                <x-icon name="check" />Approvals
                                @if ($departmentApprovalCount)
                                    <span class="ml-auto min-w-5 rounded-full bg-rose-500 px-1.5 text-center text-[11px] font-bold leading-5 text-white">{{ $departmentApprovalCount }}</span>
                                @endif
                            </a>
                        @endif
                    </nav>

                    @if ($deskWorkspace)
                        <div class="mt-7">
                            <p class="px-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Raise something new</p>
                            <div class="mt-2 space-y-1">
                                <a href="{{ route('desk.requests.create', $deskWorkspace) }}" class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('desk.requests.create') ? 'bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                                    <x-icon name="plus" />Feature
                                </a>
                                <a href="{{ route('desk.project-requests.create', $deskWorkspace) }}" class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('desk.project-requests.create') ? 'bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                                    <x-icon name="projects" />Project
                                </a>
                                <a href="{{ route('desk.supporting.create', $deskWorkspace) }}" class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('desk.supporting.*') ? 'bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}"><x-icon name="help" />Supporting</a>
                            </div>
                            {{-- The labels are now the two nouns, so the sentence carries the part
                                 the nouns no longer say: which approval path each one takes. --}}
                            <p class="mt-3 px-3 text-xs leading-5 text-slate-400">
                                A feature changes a system you already use and needs one ITD approval.
                                A project creates something new and requires scoping plus two ITD signatures.
                            </p>
                        </div>
                    @endif
                </div>

                <div class="mt-10 shrink-0 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                    <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                    <x-theme-toggle class="mt-3 w-full justify-center" />
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <x-button type="submit" variant="secondary" class="w-full">Sign out</x-button>
                    </form>
                </div>
            </aside>

            <div class="min-w-0" x-bind:inert="mobile && sidebarOpen ? true : null">
                <header class="sticky top-0 z-30 flex min-h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur md:px-7 dark:border-slate-800 dark:bg-slate-950/95">
                    <div class="flex items-center gap-3">
                        <button x-ref="sidebarTrigger" type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden dark:text-slate-300 dark:hover:bg-slate-800" x-on:click="openSidebar()" aria-label="Open navigation" x-bind:aria-expanded="sidebarOpen">☰</button>
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $deskWorkspace?->name ?? 'Orbitra' }}</p>
                            <h1 class="text-lg font-bold">@yield('page-title', 'My requests')</h1>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div data-notification-center data-url="{{ route('internal.notifications.index') }}" data-read-all-url="{{ route('internal.notifications.read-all') }}" data-workspace="{{ $deskWorkspace?->public_id }}" data-user="{{ auth()->user()->public_id }}" class="relative">
                            <button data-notification-toggle type="button" class="relative grid size-10 place-items-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900" aria-label="Notifications" aria-expanded="false">
                                <x-icon name="bell" />
                                <span data-notification-badge class="absolute -right-1 -top-1 hidden min-w-5 rounded-full bg-rose-500 px-1 text-center text-[10px] font-bold leading-5 text-white"></span>
                            </button>
                            <div data-notification-panel class="absolute right-0 top-12 z-50 hidden w-[min(92vw,380px)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                                    <div>
                                        <h2 class="font-bold">Notifications</h2>
                                        <p data-notification-summary class="text-xs text-slate-500">Loading…</p>
                                    </div>
                                    <button data-notification-read-all type="button" class="text-xs font-semibold text-orbit-700 dark:text-orbit-300">Mark all read</button>
                                </div>
                                <div data-notification-list class="max-h-96 overflow-y-auto"></div>
                            </div>
                        </div>
                        <x-avatar :src="filled(auth()->user()->avatar_path) ? route('internal.users.avatar', auth()->user()) : null" :name="auth()->user()->name" size="size-10" />
                    </div>
                </header>

                <main id="main-content" class="p-4 md:p-7">
                    <x-status />
                    @yield('content')
                </main>
            </div>
        </div>
        <x-toast />
    </body>
</html>
