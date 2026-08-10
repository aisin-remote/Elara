@extends('layouts.app')

@section('title', 'Projects')
@section('page-title', 'Projects')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><h2 class="text-2xl font-bold">Projects</h2><p class="mt-1 text-sm text-slate-500">Only projects available to your role are shown.</p></div>
        @can('create', [App\Models\Project::class, $workspace])<x-link-button href="{{ route('app.projects.create', $workspace) }}">+ New project</x-link-button>@endcan
    </div>

    <form method="GET" class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_220px_auto] dark:border-slate-800 dark:bg-slate-900">
        <x-input name="search" value="{{ $search }}" placeholder="Search projects" aria-label="Search projects" />
        <x-select name="status" aria-label="Filter by status"><option value="">All statuses</option>@foreach (['planned' => 'Planned', 'active' => 'Active', 'on_hold' => 'On hold', 'completed' => 'Completed'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</x-select>
        <x-button variant="secondary">Filter</x-button>
    </form>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($projects as $project)
            <article class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between"><span class="size-3 rounded-full" style="background: {{ $project->color ?? '#64748b' }}"></span><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold dark:bg-slate-800">{{ $project->status->label() }}</span></div>
                <h3 class="mt-5 text-lg font-bold"><a href="{{ route('app.projects.show', $project) }}" class="hover:text-orbit-700 dark:hover:text-orbit-300">{{ $project->name }}</a></h3>
                <p class="mt-2 line-clamp-2 min-h-10 text-sm text-slate-500">{{ $project->description ?: 'No description yet.' }}</p>
                <div class="mt-5 flex items-center justify-between text-xs text-slate-500"><span>Due {{ $project->due_date?->format('M j, Y') ?? 'not set' }}</span><a href="{{ route('app.projects.show', $project) }}" class="font-semibold text-orbit-700 dark:text-orbit-300">Open →</a></div>
            </article>
        @empty
            <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-900"><h3 class="font-bold">No projects found</h3><p class="mt-2 text-sm text-slate-500">Create one or adjust the filters.</p></div>
        @endforelse
    </div>
    <div class="mt-6">{{ $projects->links() }}</div>

    @if ($archivedProjects->isNotEmpty())
        <section class="mt-10" aria-labelledby="archived-title">
            <h2 id="archived-title" class="text-lg font-bold">Archived projects</h2>
            <div class="mt-4 space-y-3">
                @foreach ($archivedProjects as $project)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><div><p class="font-semibold">{{ $project->name }}</p><p class="text-xs text-slate-500">Archived {{ $project->archived_at?->diffForHumans() }}</p></div>@can('restore', $project)<form method="POST" action="{{ route('internal.projects.restore', $project) }}">@csrf<x-button variant="secondary">Restore</x-button></form>@endcan</div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
