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
        <div
            x-bind:style="! mobile ? { gridTemplateColumns: sidebarCollapsed ? '0px minmax(0, 1fr)' : '248px minmax(0, 1fr)' } : {}"
            class="min-h-screen bg-[#f7f8fb] dark:bg-slate-950 lg:grid lg:grid-cols-[248px_1fr] lg:transition-[grid-template-columns] lg:duration-200">
            <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" x-on:click="closeSidebar()" aria-hidden="true"></div>

            <aside x-ref="sidebar"
                x-bind:inert="(mobile && ! sidebarOpen) || (! mobile && sidebarCollapsed) ? true : null"
                x-on:keydown.tab="trapTab($event)"
                x-on:keydown.escape.window="closeSidebar()"
                :class="mobile
                    ? (sidebarOpen ? 'translate-x-0 opacity-100' : '-translate-x-full opacity-0')
                    : (sidebarCollapsed ? '-translate-x-full opacity-0 pointer-events-none' : 'translate-x-0 opacity-100')"
                class="fixed inset-y-0 left-0 z-50 flex w-[248px] flex-col overflow-visible border-r border-slate-200 bg-white p-2 transition-[transform,opacity] duration-200 dark:border-slate-800 dark:bg-slate-900 lg:sticky lg:top-0 lg:h-screen"
                aria-label="Workspace navigation">
                <button x-ref="sidebarCollapse" type="button"
                    class="absolute -right-4 top-3 z-20 hidden size-8 place-items-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:bg-slate-100 hover:text-slate-900 lg:grid dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    x-on:click="collapseDesktopSidebar()"
                    aria-label="Hide sidebar"
                    title="Hide sidebar">
                    <x-icon name="sidebar-collapse" class="size-4" />
                </button>

                <div class="scrollbar-none min-h-0 flex-1 overflow-y-auto">
                    <div class="flex min-h-14 items-start gap-1 px-1 py-1">
                        <x-logo size="sidebar" :href="$activeWorkspace ? route('app.workspaces.show', $activeWorkspace) : route('home')" class="min-w-0 flex-1 px-1.5 py-1.5" />

                        <button x-ref="sidebarClose" type="button" class="mt-1 grid size-7 shrink-0 place-items-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800 lg:hidden dark:hover:bg-slate-800 dark:hover:text-slate-200" x-on:click="closeSidebar()" aria-label="Close navigation">
                            <x-icon name="close" class="size-4" />
                        </button>
                    </div>

                    @if ($activeWorkspace)
                        @php
                            $routeProject = request()->route('project');
                            $routeSystem = request()->route('system');
                            $viewingSystem = $routeSystem instanceof App\Models\Project
                                || ($routeProject instanceof App\Models\Project && $routeProject->isSystem());
                            $projectCreateUrl = auth()->user()->can('create', [App\Models\Project::class, $activeWorkspace])
                                ? route('app.projects.create', $activeWorkspace)
                                : null;
                            $myTasksActive = request()->routeIs('app.tasks.index')
                                && (! filled(request('assignee')) || request('assignee') === auth()->user()->public_id);
                        @endphp

                        <nav class="mt-2 space-y-0.5" aria-label="Main navigation">
                            <x-sidebar.item :href="route('app.workspaces.show', $activeWorkspace)" icon="dashboard" :active="request()->routeIs('app.workspaces.show')">Home</x-sidebar.item>
                            <x-sidebar.item :href="route('app.ai.index', $activeWorkspace)" icon="sparkles" :active="request()->routeIs('app.ai.*')">Ask AI</x-sidebar.item>

                            @can('viewAny', [App\Models\FeatureRequest::class, $activeWorkspace])
                                <x-sidebar.item :href="route('app.approvals.index', $activeWorkspace)" icon="check" :active="request()->routeIs('app.approvals.*')" :badge="$pendingApprovals">Approvals</x-sidebar.item>
                            @endcan
                        </nav>

                        <x-sidebar.section id="work" title="Work">
                            <x-sidebar.item :href="route('app.tasks.index', $activeWorkspace)" icon="tasks" :active="$myTasksActive">My tasks</x-sidebar.item>
                            <x-sidebar.item :href="route('app.schedule.index', $activeWorkspace)" icon="calendar" :active="request()->routeIs('app.schedule.*')">Schedule</x-sidebar.item>
                            <x-sidebar.item :href="route('app.features.index', $activeWorkspace)" icon="board" :active="request()->routeIs('app.features.*') || $viewingSystem">Features</x-sidebar.item>
                            <x-sidebar.item :href="route('app.supporting.index', $activeWorkspace)" icon="supporting" :active="request()->routeIs('app.supporting.*')">Supporting</x-sidebar.item>
                        </x-sidebar.section>

                        <x-sidebar.section id="projects" title="Projects" :action-href="$projectCreateUrl" action-label="Create project">
                            @foreach ($sidebarProjects as $sidebarProject)
                                @php
                                    $projectIsCurrent = $routeProject instanceof App\Models\Project
                                        && $routeProject->is($sidebarProject);
                                @endphp
                                <x-sidebar.item :href="route('app.projects.show', $sidebarProject)" :active="$projectIsCurrent">
                                    <x-slot:lead>
                                        <span class="ml-1 size-2.5 shrink-0 rounded-sm" style="background-color: {{ $sidebarProject->color ?? '#64748b' }}"></span>
                                    </x-slot:lead>
                                    {{ $sidebarProject->name }}
                                </x-sidebar.item>
                            @endforeach
                            <x-sidebar.item :href="route('app.projects.index', $activeWorkspace)" icon="projects" :active="request()->routeIs('app.projects.index', 'app.projects.create')">All projects</x-sidebar.item>
                        </x-sidebar.section>

                        <x-sidebar.section id="team" title="Team">
                            @foreach ($sidebarTeamTaskMembers as $taskMember)
                                @php
                                    $memberTasksActive = request()->routeIs('app.tasks.index')
                                        && request('assignee') === $taskMember->public_id;
                                @endphp
                                <x-sidebar.item :href="route('app.tasks.index', ['workspace' => $activeWorkspace, 'assignee' => $taskMember->public_id])" :active="$memberTasksActive">
                                    <x-slot:lead>
                                        <x-avatar :src="filled($taskMember->avatar_path) ? route('internal.users.avatar', $taskMember) : null" :name="$taskMember->name" size="size-5" class="shrink-0 rounded-md" />
                                    </x-slot:lead>
                                    {{ $taskMember->name }}
                                </x-sidebar.item>
                            @endforeach
                            <x-sidebar.item :href="route('app.workspaces.team', $activeWorkspace)" icon="team" :active="request()->routeIs('app.workspaces.team')">All team</x-sidebar.item>
                        </x-sidebar.section>

                        <x-sidebar.section id="more" title="More">
                            <x-sidebar.item :href="route('app.performance.index', $activeWorkspace)" icon="performance" :active="request()->routeIs('app.performance.*')">Performance</x-sidebar.item>
                            <x-sidebar.item :href="route('app.messages.index', $activeWorkspace)" icon="messages" :active="request()->routeIs('app.messages.*')">Messages</x-sidebar.item>
                        </x-sidebar.section>
                    @endif
                </div>

                <div class="mt-2 shrink-0 border-t border-slate-200 pt-2 dark:border-slate-800">
                    @if ($activeWorkspace)
                        <x-sidebar.item :href="route('app.settings.profile', $activeWorkspace)" icon="settings" :active="request()->routeIs('app.settings.*', 'app.workspaces.settings')">Settings</x-sidebar.item>
                    @endif

                    <div class="mt-1 flex items-center gap-2 rounded-md px-2 py-1.5">
                        <x-avatar :src="filled(auth()->user()->avatar_path) ? route('internal.users.avatar', auth()->user()) : null" :name="auth()->user()->name" size="size-6" class="shrink-0 rounded-md" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-medium text-slate-700 dark:text-slate-200">{{ auth()->user()->name }}</p>
                            <p class="truncate text-[11px] text-slate-400 dark:text-slate-500">{{ auth()->user()->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="rounded px-1.5 py-1 text-[10px] font-medium text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200">Sign out</button>
                        </form>
                    </div>
                </div>
            </aside>

            <div class="min-w-0" x-bind:inert="mobile && sidebarOpen ? true : null">
                <header class="sticky top-0 z-30 flex min-h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur md:px-7 dark:border-slate-800 dark:bg-slate-950/95">
                    <div class="flex items-center gap-3">
                        <button x-ref="sidebarTrigger" type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden dark:text-slate-300 dark:hover:bg-slate-800" x-on:click="openSidebar()" aria-label="Open navigation" x-bind:aria-expanded="sidebarOpen">☰</button>
                        <button x-ref="sidebarExpand" x-cloak x-show="! mobile && sidebarCollapsed" type="button"
                            class="hidden size-8 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:bg-slate-100 hover:text-slate-900 lg:grid dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                            x-on:click="expandDesktopSidebar()"
                            aria-label="Show sidebar"
                            title="Show sidebar">
                            <x-icon name="sidebar-expand" class="size-4" />
                        </button>
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $activeWorkspace?->name ?? 'Orbitra' }}</p>
                            <h1 class="text-lg font-bold">@yield('page-title', 'Dashboard')</h1>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($activeWorkspace)
                            <button type="button" x-on:click="$dispatch('open-global-search')" class="grid size-10 place-items-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 md:hidden dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900" aria-label="Open global search"><x-icon name="search" /></button>
                            <button type="button" x-on:click="$dispatch('open-global-search')" class="hidden h-10 w-72 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-medium text-slate-600 hover:border-slate-300 hover:bg-white md:flex dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:bg-slate-800" aria-label="Open global search">
                                <x-icon name="search" class="text-slate-400" />
                                <span>Search</span>
                                <kbd class="ml-auto rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-semibold text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500">Ctrl K</kbd>
                            </button>
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
        @if ($activeWorkspace)
            <x-global-search :workspace="$activeWorkspace" />
        @endif
        <x-toast />
    </body>
</html>
