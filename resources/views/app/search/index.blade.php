@extends('layouts.app')

@section('title', 'Search')
@section('page-title', 'Search')

@section('content')
    <div>
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900">
            <div class="max-w-2xl"><p class="text-sm font-semibold text-orbit-600">Global search</p><h2 class="mt-1 text-3xl font-bold tracking-tight">Find work you can access</h2><p class="mt-2 text-sm leading-6 text-slate-500">Projects, tasks, private file metadata, workspace members, and your conversations are searched together.</p></div>
            <form method="GET" action="{{ route('app.search', $workspace) }}" class="mt-6 flex flex-col gap-3 sm:flex-row">
                <label class="relative flex-1"><span class="sr-only">Search this workspace</span><x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input type="search" name="q" value="{{ $query }}" minlength="2" maxlength="100" autofocus class="min-h-12 w-full rounded-xl border-slate-300 bg-white pl-10 pr-4 dark:border-slate-700 dark:bg-slate-950" placeholder="Search projects, tasks, files, people, or conversations…"></label>
                <x-button class="sm:px-7">Search</x-button>
            </form>
            <x-field-error name="q" />
        </section>

        @if($results)
            <section class="mt-6" aria-labelledby="search-results-title">
                <div class="flex items-center justify-between gap-4"><h2 id="search-results-title" class="text-lg font-bold">{{ $results->total() }} {{ Str::plural('result', $results->total()) }} for “{{ $query }}”</h2><span class="text-sm text-slate-500">Page {{ $results->currentPage() }} of {{ $results->lastPage() }}</span></div>
                <div class="mt-4 space-y-3">
                    @forelse($results as $result)
                        @php($parts = preg_split('/('.preg_quote($query, '/').')/iu', $result->label, -1, PREG_SPLIT_DELIM_CAPTURE))
                        <a href="{{ $result->url }}" class="flex min-h-20 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-orbit-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-orbit-700">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-orbit-50 text-orbit-700 dark:bg-orbit-950 dark:text-orbit-300"><x-icon :name="match($result->result_type) { 'project' => 'projects', 'task' => 'tasks', 'file' => 'files', 'member' => 'team', default => 'messages' }" /></span>
                            <span class="min-w-0 flex-1"><span class="block text-xs font-bold uppercase tracking-wide text-slate-400">{{ $result->result_type }}</span><strong class="mt-1 block truncate">@foreach($parts as $part)@if(mb_strtolower($part) === mb_strtolower($query))<mark class="rounded bg-amber-200 px-0.5 text-slate-950">{{ $part }}</mark>@else{{ $part }}@endif @endforeach</strong><span class="mt-1 block truncate text-sm text-slate-500">{{ $result->description }}</span></span><span class="text-slate-400" aria-hidden="true">→</span>
                        </a>
                    @empty
                        <x-empty-state title="No accessible results" description="Try a broader term or check another workspace." />
                    @endforelse
                </div>
                <x-pagination :paginator="$results" class="mt-6" />
            </section>
        @endif
    </div>
@endsection
