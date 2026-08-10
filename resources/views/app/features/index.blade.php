@extends('layouts.app')

@section('title', 'Features')
@section('page-title', 'Features')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold">Systems</h2>
            <p class="mt-1 text-sm text-slate-500">Standing systems you maintain, and the feature work queued inside each.</p>
        </div>
        @can('manageMasterData', $workspace)
            <x-link-button href="{{ route('app.settings.master.systems', $workspace) }}" variant="secondary">Manage systems</x-link-button>
        @endcan
    </div>

    <form method="GET" class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_220px_auto] dark:border-slate-800 dark:bg-slate-900">
        <x-input name="search" value="{{ $search }}" placeholder="Search systems" aria-label="Search systems" />
        <x-select name="pic" aria-label="Filter by PIC">
            <option value="">All PICs</option>
            @foreach ($pics as $option)
                <option value="{{ $option->public_id }}" @selected($pic === $option->public_id)>{{ $option->name }}</option>
            @endforeach
        </x-select>
        <div class="flex gap-2">
            <x-button variant="secondary">Filter</x-button>
            @if ($search !== '' || $pic !== '')
                <x-link-button href="{{ route('app.features.index', $workspace) }}" variant="secondary">Clear</x-link-button>
            @endif
        </div>
    </form>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($systems as $entry)
            @php($system = $entry['model'])
            @php($progress = $entry['progress'])
            <a href="{{ route('app.features.show', [$workspace, $system]) }}" class="group rounded-3xl border border-slate-200 bg-white p-5 transition hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
                <div class="flex items-start justify-between gap-3">
                    <span class="size-3 shrink-0 rounded-full" style="background: {{ $system->color ?? '#64748b' }}"></span>
                    <span class="flex -space-x-1.5">
                        @foreach ($system->members->take(3) as $member)
                            <x-avatar :src="filled($member->avatar_path) ? route('internal.users.avatar', $member) : null" :name="$member->name" size="size-7" class="border-2 border-white dark:border-slate-900" />
                        @endforeach
                    </span>
                </div>

                <h3 class="mt-4 text-lg font-bold">{{ $system->name }}</h3>
                <p class="mt-0.5 text-xs text-slate-500">PIC: {{ $entry['pic']?->name ?? 'nobody yet' }}</p>
                <p class="mt-2 line-clamp-2 min-h-10 text-sm text-slate-500">{{ $system->description ?: 'No description yet.' }}</p>

                <dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800/60">
                    <div><dt class="text-xs text-slate-500">Active features</dt><dd class="mt-1 font-semibold">{{ $system->active_features_count }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Open tasks</dt><dd class="mt-1 font-semibold">{{ $system->open_tasks_count }}</dd></div>
                </dl>

                <div class="mt-4">
                    <div class="flex justify-between text-[11px] text-slate-500"><span>Progress</span><span>{{ $progress['percentage'] }}%</span></div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full rounded-full bg-orbit-500" style="width: {{ $progress['percentage'] }}%"></div>
                    </div>
                </div>
            </a>
        @empty
            <x-empty-state
                icon="projects"
                :title="$search !== '' || $pic !== '' ? 'No systems match that' : 'No systems yet'"
                :description="$search !== '' || $pic !== ''
                    ? 'Clear the search or the PIC filter to see the rest.'
                    : 'Add the systems your team maintains in Settings → Master data. Feature requests are raised against them.'"
                class="md:col-span-2 xl:col-span-3" />
        @endforelse
    </div>
@endsection
