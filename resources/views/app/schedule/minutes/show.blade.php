@extends('layouts.app')

@section('title', $meetingMinute->title)
@section('page-title', 'MOM')

@section('content')
    @include('app.schedule._tabs')

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><p class="text-sm text-slate-500">Schedule / MOM</p><h2 class="mt-1 text-2xl font-bold tracking-tight">{{ $meetingMinute->title }}</h2></div>
        <div class="flex flex-wrap items-center gap-2">
            <x-badge :tone="match($meetingMinute->publication_status) { App\Enums\MeetingMinutePublicationStatus::DRAFT => 'warning', App\Enums\MeetingMinutePublicationStatus::LOCKED => 'slate', default => 'success' }">{{ $meetingMinute->publication_status->label() }}</x-badge>
            @can('update', $meetingMinute)<x-link-button href="{{ route('app.schedule.minutes.edit', [$workspace, $meetingMinute]) }}" variant="secondary">Edit MOM</x-link-button>@endcan
            @can('managePublication', $meetingMinute)
                @php($nextLifecycle = match($meetingMinute->publication_status) { App\Enums\MeetingMinutePublicationStatus::DRAFT => ['published', 'Publish'], App\Enums\MeetingMinutePublicationStatus::PUBLISHED => ['locked', 'Lock'], default => ['published', 'Unlock'] })
                <form method="POST" action="{{ route('internal.meeting-minutes.publication', $meetingMinute) }}">@csrf @method('PATCH')<input type="hidden" name="publication_status" value="{{ $nextLifecycle[0] }}"><x-button variant="secondary">{{ $nextLifecycle[1] }}</x-button></form>
            @endcan
        </div>
    </div>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-4">
            <div><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Meeting date</p><p class="mt-1 font-bold">{{ $meetingMinute->meeting_at->format('M j, Y · H:i') }}</p></div>
            <div><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Recorded by</p><p class="mt-1 font-bold">{{ $meetingMinute->creator->name }}</p></div>
            <div><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Schedule</p><p class="mt-1 font-bold">{{ $meetingMinute->scheduleEvent?->title ?? 'Independent MOM' }}</p></div>
            <div><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Project / system</p><p class="mt-1 font-bold">{{ $meetingMinute->project?->name ?? 'General' }}</p>@if($meetingMinute->project)<p class="mt-1 text-xs text-slate-500">{{ $meetingMinute->project->type->label() }}</p>@endif</div>
        </div>
        @if ($meetingMinute->summary)
            <div class="border-t border-slate-200 p-5 dark:border-slate-800"><h3 class="font-bold">Summary</h3><p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $meetingMinute->summary }}</p></div>
        @endif
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="text-lg font-bold">Action items</h3><p class="mt-1 text-sm text-slate-500">{{ $meetingMinute->items->where('status', App\Enums\MeetingMinuteStatus::DONE)->count() }} of {{ $meetingMinute->items->count() }} completed</p></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-950/60"><tr><th class="px-5 py-3">Action / decision</th><th class="px-5 py-3">PIC</th><th class="px-5 py-3">Due date</th><th class="px-5 py-3">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($meetingMinute->items as $item)
                        @php($statusClass = match($item->status) { App\Enums\MeetingMinuteStatus::DONE => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300', App\Enums\MeetingMinuteStatus::IN_PROGRESS => 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300', App\Enums\MeetingMinuteStatus::PENDING => 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300', default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' })
                        <tr class="align-top">
                            <td class="max-w-md whitespace-pre-line px-5 py-4 font-semibold leading-6">{{ $item->content }}</td>
                            <td class="px-5 py-4"><p class="font-bold">{{ $item->pic_name }}</p>@if($item->pic)<p class="mt-1 text-xs text-slate-500">IT member</p>@endif</td>
                            <td class="whitespace-nowrap px-5 py-4 font-semibold {{ $item->due_date?->isPast() && $item->status !== App\Enums\MeetingMinuteStatus::DONE ? 'text-rose-600' : '' }}">{{ $item->due_date?->format('M j, Y') ?? 'TBA' }}</td>
                            <td class="px-5 py-4">
                                @can('update', $item)
                                    <form method="POST" action="{{ route('internal.meeting-minute-items.update', $item) }}" class="flex items-center gap-2">@csrf @method('PATCH')<select name="status" class="rounded-lg border-slate-300 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950">@foreach(App\Enums\MeetingMinuteStatus::cases() as $statusOption)<option value="{{ $statusOption->value }}" @selected($item->status === $statusOption)>{{ $statusOption->label() }}</option>@endforeach</select><button class="text-xs font-bold text-orbit-700 dark:text-orbit-300">Save</button></form>
                                @else
                                    <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ $item->status->label() }}</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between"><div><h3 class="text-lg font-bold">Revision history</h3><p class="mt-1 text-sm text-slate-500">An immutable snapshot is kept whenever this MOM changes.</p></div><span class="text-sm font-bold text-slate-500">{{ $meetingMinute->revisions->count() }} versions</span></div>
        <ol class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">@forelse($meetingMinute->revisions->take(5) as $revision)<li class="flex items-center justify-between gap-3 py-3 text-sm"><span><strong>Version {{ $revision->revision }}</strong> · {{ $revision->editor?->name ?? 'Deleted user' }}</span><time class="text-xs text-slate-500">{{ $revision->created_at->diffForHumans() }}</time></li>@empty<li class="py-3 text-sm text-slate-500">No revisions recorded yet.</li>@endforelse</ol>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between gap-3"><div><h3 class="text-lg font-bold">Documents</h3><p class="mt-1 text-sm text-slate-500">Files are private to authorized workspace members.</p></div>@can('update', $meetingMinute)<a href="{{ route('app.schedule.minutes.edit', [$workspace, $meetingMinute]) }}" class="text-sm font-bold text-orbit-700 dark:text-orbit-300">Add documents</a>@endcan</div>
        <div class="mt-4 space-y-2">
            @forelse ($meetingMinute->files as $file)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700"><div class="min-w-0"><a href="{{ route('internal.files.download', $file) }}" class="block truncate text-sm font-bold hover:text-orbit-700 dark:hover:text-orbit-300">{{ $file->original_name }}</a><p class="mt-1 text-xs text-slate-500">{{ number_format($file->size / 1024, 1) }} KB · {{ $file->uploader->name }}</p></div>@can('delete', $file)<form method="POST" action="{{ route('internal.files.destroy', $file) }}" onsubmit="return confirm('Delete this document?')">@csrf @method('DELETE')<input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}"><button class="text-xs font-bold text-rose-600">Delete</button></form>@endcan</div>
            @empty
                <p class="text-sm text-slate-500">No documents attached.</p>
            @endforelse
        </div>
    </section>

    @can('delete', $meetingMinute)<form method="POST" action="{{ route('internal.meeting-minutes.destroy', $meetingMinute) }}" class="mt-8 border-t border-slate-200 pt-6 dark:border-slate-800" onsubmit="return confirm('Delete this MOM?')">@csrf @method('DELETE')<x-button variant="danger">Delete MOM</x-button></form>@endcan
    <x-discussion :subject="$meetingMinute" />
@endsection
