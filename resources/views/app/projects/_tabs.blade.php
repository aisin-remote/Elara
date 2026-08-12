<nav class="mt-6 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Project views">
    @foreach ([
        ['app.projects.show', 'Overview', 'dashboard', [$project]],
        ['app.projects.tasks', 'List', 'list', [$project->workspace, $project]],
        ['app.projects.timeline', 'Timeline', 'performance', [$project->workspace, $project]],
        ['app.projects.files', 'Files', 'files', [$project->workspace, $project]],
    ] as [$routeName, $label, $icon, $parameters])
        <a href="{{ route($routeName, $parameters) }}" @if(request()->routeIs($routeName)) aria-current="page" @endif class="inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-4 text-sm font-semibold {{ request()->routeIs($routeName) ? 'border-orbit-600 text-orbit-700' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
            <x-icon :name="$icon" />{{ $label }}
        </a>
    @endforeach
</nav>
