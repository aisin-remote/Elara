@extends('layouts.app')

@section('title', 'MOM')
@section('page-title', 'Schedule')

@section('content')
    @include('app.schedule._tabs')

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500">Decisions, follow-ups, and meeting documents in one place.</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">MOM</h2>
        </div>
        @can('create', [App\Models\MeetingMinute::class, $workspace])
            <x-link-button href="{{ route('app.schedule.minutes.create', $workspace) }}"><x-icon name="plus" />New MOM</x-link-button>
        @endcan
    </div>

    <form method="GET" class="mt-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-3 xl:grid-cols-6 dark:border-slate-800 dark:bg-slate-900">
        <x-input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search MOM" />
        <x-select name="lifecycle"><option value="">All lifecycles</option>@foreach(App\Enums\MeetingMinutePublicationStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['lifecycle'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</x-select>
        <x-select name="project"><option value="">All projects</option>@foreach($projects as $project)<option value="{{ $project->public_id }}" @selected(($filters['project'] ?? '') === $project->public_id)>{{ $project->name }}</option>@endforeach</x-select>
        <x-select name="pic"><option value="">All PICs</option>@foreach($picUsers as $pic)<option value="{{ $pic->public_id }}" @selected(($filters['pic'] ?? '') === $pic->public_id)>{{ $pic->name }}</option>@endforeach</x-select>
        <x-select name="action_status"><option value="">All action statuses</option>@foreach(App\Enums\MeetingMinuteStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['action_status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</x-select>
        <div class="flex gap-2"><x-button class="flex-1">Filter</x-button>@if($filters)<x-link-button href="{{ route('app.schedule.minutes.index', $workspace) }}" variant="secondary">Clear</x-link-button>@endif</div>
    </form>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1080px] text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-950/60">
                    <tr>
                        <th class="px-5 py-3">MOM</th>
                        <th class="px-4 py-3">Project / system</th>
                        <th class="px-4 py-3">Meeting date</th>
                        <th class="px-4 py-3">Recorded by</th>
                        <th class="px-4 py-3">Lifecycle</th>
                        <th class="px-4 py-3">Action items</th>
                        <th class="px-4 py-3">Documents</th>
                        <th class="w-12 px-4 py-3"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($minutes as $minute)
                        <tr class="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                            <td class="max-w-sm px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('app.schedule.minutes.show', [$workspace, $minute]) }}" class="truncate font-bold hover:text-orbit-700 dark:hover:text-orbit-300">{{ $minute->title }}</a>
                                    @if ($minute->done_items_count === $minute->items_count && $minute->items_count > 0)<span class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">All done</span>@endif
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                @if ($minute->project)
                                    <p class="font-semibold">{{ $minute->project->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $minute->project->type->label() }}</p>
                                @else
                                    <span class="text-slate-500">General</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4"><p class="font-semibold">{{ $minute->meeting_at->format('M j, Y') }}</p><p class="mt-1 text-xs text-slate-500">{{ $minute->meeting_at->format('H:i') }}</p></td>
                            <td class="px-4 py-4 font-semibold">{{ $minute->creator->name }}</td>
                            <td class="px-4 py-4"><x-badge :tone="match($minute->publication_status) { App\Enums\MeetingMinutePublicationStatus::DRAFT => 'warning', App\Enums\MeetingMinutePublicationStatus::LOCKED => 'slate', default => 'success' }">{{ $minute->publication_status->label() }}</x-badge></td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <strong>{{ $minute->done_items_count }}/{{ $minute->items_count }}</strong> done
                                @if ($minute->tba_items_count)<p class="mt-1 text-xs text-slate-500">{{ $minute->tba_items_count }} TBA</p>@endif
                            </td>
                            <td class="px-4 py-4">{{ $minute->files_count ?: '—' }}</td>
                            <td class="px-4 py-4"><a href="{{ route('app.schedule.minutes.show', [$workspace, $minute]) }}" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-orbit-700 dark:hover:bg-slate-800 dark:hover:text-orbit-300" aria-label="Open {{ $minute->title }}"><x-icon name="chevron-right" class="size-4" /></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-16 text-center"><div class="mx-auto grid size-12 place-items-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800"><x-icon name="calendar" /></div><h3 class="mt-4 font-bold">No meeting minutes yet</h3><p class="mt-1 text-sm text-slate-500">Create one after a meeting to keep decisions and follow-ups traceable.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($minutes->hasPages())<div class="mt-6">{{ $minutes->links() }}</div>@endif
@endsection
