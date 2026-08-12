@php
    $feature = $feature ?? null;
    $featureQuery = $feature ? ['feature' => $feature->public_id] : [];
    $tabs = [
        [$feature ? 'app.features.detail' : 'app.features.show', 'Overview', 'dashboard', $feature ? [$workspace, $system, $feature] : [$workspace, $system]],
        ['app.projects.tasks', 'List', 'list', ['workspace' => $workspace, 'project' => $system] + $featureQuery],
        ['app.projects.timeline', 'Timeline', 'performance', ['workspace' => $workspace, 'project' => $system] + $featureQuery],
    ];

    if (! $feature) {
        $tabs[] = ['app.projects.files', 'Files', 'files', [$workspace, $system]];
    }
@endphp

<nav class="mt-6 flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-800" aria-label="{{ $feature ? 'Feature' : 'System' }} views">
    @foreach ($tabs as [$routeName, $label, $icon, $parameters])
        <a href="{{ route($routeName, $parameters) }}" @if(request()->routeIs($routeName)) aria-current="page" @endif class="inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-4 text-sm font-semibold {{ request()->routeIs($routeName) ? 'border-orbit-600 text-orbit-700 dark:text-orbit-300' : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-white' }}">
            <x-icon :name="$icon" />{{ $label }}
        </a>
    @endforeach
</nav>
