<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="themePreference" data-theme="{{ auth()->user()->theme }}" data-theme-endpoint="{{ route('internal.settings.theme.update') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Dashboard') · Orbitra</title>
        <script>
            const orbitraTheme = localStorage.getItem('orbitra-theme') ?? @json(auth()->user()->theme);
            document.documentElement.classList.toggle('dark', orbitraTheme === 'dark' || (orbitraTheme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches));
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-user-name="{{ auth()->user()->name }}" x-data="appShell">
        <a href="#main-content" class="sr-only z-[100] rounded-lg bg-white px-4 py-2 font-semibold text-slate-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Skip to content</a>
        <x-connectivity-status />
        <div class="min-h-screen bg-[#f7f8fb] lg:grid lg:grid-cols-[248px_1fr] dark:bg-slate-950">
            <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" x-on:click="closeSidebar()" aria-hidden="true"></div>

            <aside x-ref="sidebar" x-bind:inert="mobile && ! sidebarOpen ? true : null" x-on:keydown.tab="trapTab($event)" x-on:keydown.escape.window="closeSidebar()" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" {{-- lg:h-screen keeps the sidebar one viewport tall instead of stretching to match the page. --}}
            class="fixed inset-y-0 left-0 z-50 flex w-[248px] flex-col overflow-hidden border-r border-slate-200 bg-white p-4 transition-transform dark:border-slate-800 dark:bg-slate-900 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0" aria-label="Workspace navigation">
                {{-- Only this part scrolls; the account footer below stays pinned. --}}
                <div class="scrollbar-none min-h-0 flex-1 overflow-y-auto">
                <div class="flex items-center justify-between">
                    <x-logo />
                    <button x-ref="sidebarClose" type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-800" x-on:click="closeSidebar()" aria-label="Close navigation">✕</button>
                </div>

                <div class="mt-7">
                    <label for="workspace-switcher" class="px-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Workspace</label>
                    @if ($workspaceOptions->isNotEmpty())
                        <select id="workspace-switcher" class="mt-2 block min-h-11 w-full rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900" x-on:change="if ($event.target.value) window.location = $event.target.value">
                            @foreach ($workspaceOptions as $workspaceOption)
                                <option value="{{ route('app.workspaces.show', $workspaceOption) }}" @selected($activeWorkspace?->is($workspaceOption))>{{ $workspaceOption->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <p class="mt-2 rounded-xl bg-slate-50 p-3 text-sm text-slate-500 dark:bg-slate-800 dark:text-slate-400">No workspace yet.</p>
                    @endif
                    <a href="{{ route('app.workspaces.create') }}" class="mt-2 flex min-h-11 items-center justify-center rounded-xl border border-dashed border-slate-300 px-3 text-sm font-semibold text-slate-600 hover:border-orbit-400 hover:text-orbit-700 dark:border-slate-700 dark:text-slate-300">+ New workspace</a>
                </div>

                @if ($activeWorkspace)
                    @php
                        // Grouped by the question each screen answers, not by feature area.
                        // Dashboard and Approvals sit ungrouped at the top because they are the
                        // two "what needs me right now" screens — a heading above them would
                        // only push the badge further from the eye.
                        // Its own variable rather than a null key in the grouped array: PHP turns
                        // a null array key into an empty string, so array_filter would keep it
                        // and Dashboard would render twice.
                        $primaryNavigation = [
                            ['app.workspaces.show', 'Dashboard', 'dashboard'],
                        ];

                        $navigation = [
                            'Delivery' => [
                                ['app.projects.index', 'Projects', 'projects'],
                                ['app.features.index', 'Features', 'board'],
                                ['app.tasks.index', 'Task List', 'tasks'],
                                ['app.supporting.index', 'Supporting', 'supporting'],
                                ['app.schedule.index', 'Schedule', 'calendar'],
                            ],
                            'Insight' => [
                                ['app.performance.index', 'Performance', 'performance'],
                                ['app.ai.index', 'Ask AI', 'sparkles'],
                            ],
                            'People' => [
                                ['app.workspaces.team', 'Team', 'team'],
                                ['app.messages.index', 'Messages', 'messages'],
                            ],
                        ];
                    @endphp
                    @php
                        // A system's board and task list ride the app.projects.* routes, so the
                        // bound record decides which menu lights up, not the route name alone.
                        $routeRecord = request()->route('system') ?? request()->route('project');
                        $viewingSystem = $routeRecord instanceof App\Models\Project && $routeRecord->isSystem();
                    @endphp
                    <nav class="mt-7 space-y-1" aria-label="Main navigation">
                        @foreach ($primaryNavigation as [$routeName, $label, $icon])
                            <a href="{{ route($routeName, $activeWorkspace) }}" @if (request()->routeIs($routeName)) aria-current="page" @endif class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs($routeName) ? 'bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                                <x-icon :name="$icon" />{{ $label }}
                            </a>
                        @endforeach
                        @can('viewAny', [App\Models\FeatureRequest::class, $activeWorkspace])
                            @php($pendingApprovals = App\Models\FeatureRequest::where('workspace_id', $activeWorkspace->id)->awaitingReview()->count()
                                + App\Models\ProjectRequest::where('workspace_id', $activeWorkspace->id)->awaitingDecision()->count()
                                + App\Models\TaskBreakdown::where('workspace_id', $activeWorkspace->id)->where('status', App\Enums\BreakdownStatus::READY->value)->count())
                            <a href="{{ route('app.approvals.index', $activeWorkspace) }}" @if(request()->routeIs('app.approvals.*')) aria-current="page" @endif class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('app.approvals.*') ? 'bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                                <x-icon name="check" />Approvals
                                @if ($pendingApprovals > 0)
                                    <span class="ml-auto min-w-5 rounded-full bg-rose-500 px-1.5 text-center text-[11px] font-bold leading-5 text-white">{{ $pendingApprovals }}</span>
                                @endif
                            </a>
                        @endcan
                    </nav>

                    @foreach ($navigation as $heading => $items)
                        <div class="mt-6">
                            <p class="px-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $heading }}</p>
                            <div class="mt-2 space-y-1">
                                @foreach ($items as [$routeName, $label, $icon])
                                    @php($isCurrent = request()->routeIs($routeName)
                                        || ($routeName === 'app.projects.index' && request()->routeIs('app.projects.*') && ! $viewingSystem)
                                        || ($routeName === 'app.features.index' && ($viewingSystem || request()->routeIs('app.features.*')))
                                        || ($routeName === 'app.tasks.index' && request()->routeIs('app.tasks.*'))
                                        || ($routeName === 'app.supporting.index' && request()->routeIs('app.supporting.*')))
                                    <a href="{{ route($routeName, $activeWorkspace) }}" @if ($isCurrent) aria-current="page" @endif class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ $isCurrent ? 'bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                                        <x-icon :name="$icon" />{{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="mt-6">
                        <a href="{{ route('app.settings.profile', $activeWorkspace) }}" class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('app.settings.*', 'app.workspaces.settings') ? 'bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            <x-icon name="settings" />Settings
                        </a>
                    </div>

                    @if ($sidebarProjects->isNotEmpty())
                        <div class="mt-7">
                            <p class="px-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Quick access</p>
                            <div class="mt-2 space-y-1">
                                @foreach ($sidebarProjects as $sidebarProject)
                                    <a href="{{ route('app.projects.show', $sidebarProject) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                                        <span class="size-2 rounded-full" style="background-color: {{ $sidebarProject->color ?? '#64748b' }}"></span>
                                        <span class="truncate">{{ $sidebarProject->name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
                </div>

                <div class="mt-10 shrink-0 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                    <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
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
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $activeWorkspace?->name ?? 'Orbitra' }}</p>
                            <h1 class="text-lg font-bold">@yield('page-title', 'Dashboard')</h1>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($activeWorkspace)
                            <a href="{{ route('app.search', $activeWorkspace) }}" class="grid size-10 place-items-center rounded-xl border border-slate-200 text-slate-600 md:hidden dark:border-slate-700 dark:text-slate-300" aria-label="Search workspace"><x-icon name="search" /></a>
                            <form method="GET" action="{{ route('app.search', $activeWorkspace) }}" class="relative hidden md:block">
                                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                <input name="q" minlength="2" class="h-10 w-60 rounded-xl border-slate-200 bg-slate-50 pl-9 pr-3 text-sm focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-900" placeholder="Search workspace…" aria-label="Search workspace">
                            </form>
                        @endif
                        <div data-notification-center data-url="{{ route('internal.notifications.index') }}" data-read-all-url="{{ route('internal.notifications.read-all') }}" data-workspace="{{ $activeWorkspace?->public_id }}" data-user="{{ auth()->user()->public_id }}" class="relative">
                            <button data-notification-toggle type="button" class="relative grid size-10 place-items-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900" aria-label="Notifications" aria-expanded="false">
                                <x-icon name="bell" />
                                <span data-notification-badge class="absolute -right-1 -top-1 hidden min-w-5 rounded-full bg-rose-500 px-1 text-center text-[10px] font-bold leading-5 text-white"></span>
                            </button>
                            <div data-notification-panel class="absolute right-0 top-12 z-50 hidden w-[min(92vw,380px)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800"><div><h2 class="font-bold">Notifications</h2><p data-notification-summary class="text-xs text-slate-500">Loading…</p></div><button data-notification-read-all type="button" class="text-xs font-semibold text-orbit-700 dark:text-orbit-300">Mark all read</button></div>
                                <div data-notification-list class="max-h-96 overflow-y-auto"></div>
                                @if ($activeWorkspace)
                                    <a href="{{ route('app.settings.notifications', $activeWorkspace) }}" class="block border-t border-slate-200 px-4 py-3 text-center text-sm font-semibold text-orbit-700 dark:border-slate-800 dark:text-orbit-300">Notification settings</a>
                                @endif
                            </div>
                        </div>
                    <x-theme-toggle class="hidden sm:inline-flex" /></div>
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
