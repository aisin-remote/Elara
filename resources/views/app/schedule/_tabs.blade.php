<nav class="mb-6 flex border-b border-slate-200 dark:border-slate-800" aria-label="Schedule sections">
    <a href="{{ route('app.schedule.index', $workspace) }}" class="border-b-2 px-5 py-3 text-sm font-bold transition {{ request()->routeIs('app.schedule.index') ? 'border-orbit-500 text-orbit-700 dark:text-orbit-300' : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-white' }}">Calendar</a>
    <a href="{{ route('app.schedule.minutes.index', $workspace) }}" class="border-b-2 px-5 py-3 text-sm font-bold transition {{ request()->routeIs('app.schedule.minutes.*') ? 'border-orbit-500 text-orbit-700 dark:text-orbit-300' : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-white' }}">MOM</a>
</nav>
