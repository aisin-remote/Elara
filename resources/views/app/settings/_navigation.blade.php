<nav class="mb-6 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Settings sections">
    @php
        $settingsLinks = [
            ['app.settings.profile', 'Profile'],
            ['app.settings.security', 'Security'],
            ['app.settings.notifications', 'Notifications'],
        ];
        if (auth()->user()->can('manageSettings', $workspace)) {
            array_unshift($settingsLinks, ['app.workspaces.settings', 'Workspace']);
            $settingsLinks[] = ['app.settings.master', 'Master data'];
            $settingsLinks[] = ['app.settings.integrations', 'Integrations'];
        }
        if (auth()->user()->can('viewSystemHealth')) {
            $settingsLinks[] = ['app.settings.system-health', 'System health'];
        }
    @endphp
    @foreach ($settingsLinks as [$routeName, $label])
        @if (Route::has($routeName))
            <a href="{{ route($routeName, $workspace) }}" @if(request()->routeIs($routeName)) aria-current="page" @endif class="whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold {{ request()->routeIs($routeName) ? 'bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white' }}">{{ $label }}</a>
        @endif
    @endforeach
</nav>
