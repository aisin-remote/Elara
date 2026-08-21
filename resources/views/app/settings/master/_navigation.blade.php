@php
    // Holidays are scheduler-managed. Help articles, capacity, and request rules stay hidden for now.
    $masterLinks = [
        ['app.settings.master', 'Overview'],
        ['app.settings.master.departments', 'Departments'],
        ['app.settings.master.systems', 'Systems'],
        ['app.settings.master.categories', 'Task categories'],
        ['app.settings.master.status-templates', 'Workflow'],
    ];
@endphp

<div class="mb-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-orbit-600">Master data</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">@yield('master-title', 'Reference data')</h2>
            <p class="mt-1 text-sm text-slate-500">Edited here so nothing needs a database client or a deploy.</p>
        </div>
    </div>

    <nav class="mt-5 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Master data sections">
        @foreach ($masterLinks as [$routeName, $label])
            <a href="{{ route($routeName, $workspace) }}" @if(request()->routeIs($routeName)) aria-current="page" @endif class="whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold {{ request()->routeIs($routeName) ? 'bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white' }}">{{ $label }}</a>
        @endforeach
    </nav>
</div>
