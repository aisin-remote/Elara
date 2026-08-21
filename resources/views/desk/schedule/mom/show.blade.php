@extends('layouts.requester')

@section('title', $meetingMinute->title)
@section('page-title', 'MOM')

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><p class="text-sm text-slate-500">Schedule / MOM</p><h2 class="mt-1 text-2xl font-bold tracking-tight">{{ $meetingMinute->title }}</h2><p class="mt-2 text-sm text-slate-500">{{ $meetingMinute->meeting_at->format('M j, Y · H:i') }} · Recorded by {{ $meetingMinute->creator->name }}</p><p class="mt-1 text-sm font-semibold text-slate-600 dark:text-slate-300">Project / system: {{ $meetingMinute->project?->name ?? 'General' }}</p></div>
        <x-link-button href="{{ route('desk.schedule.index', $requesterWorkspace) }}" variant="secondary">Back to Schedule</x-link-button>
    </div>

    @if ($meetingMinute->summary)<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><h3 class="font-bold">Summary</h3><p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $meetingMinute->summary }}</p></section>@endif

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="text-lg font-bold">Action items</h3></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[700px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-950/60"><tr><th class="px-5 py-3">Action / decision</th><th class="px-5 py-3">PIC</th><th class="px-5 py-3">Due date</th><th class="px-5 py-3">Status</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">@foreach($meetingMinute->items as $item)<tr><td class="px-5 py-4 font-semibold">{{ $item->content }}</td><td class="px-5 py-4">{{ $item->pic_name }}</td><td class="px-5 py-4">{{ $item->due_date?->format('M j, Y') ?? 'TBA' }}</td><td class="px-5 py-4">{{ $item->status->label() }}</td></tr>@endforeach</tbody></table></div>
    </section>

    @if ($meetingMinute->files->isNotEmpty())
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><h3 class="text-lg font-bold">Documents</h3><div class="mt-4 space-y-2">@foreach($meetingMinute->files as $file)<a href="{{ route('desk.schedule.mom.files.download', [$requesterWorkspace, $meetingMinute->public_id, $file->public_id]) }}" class="flex items-center justify-between rounded-xl border border-slate-200 p-3 text-sm font-bold hover:border-orbit-400 dark:border-slate-700">{{ $file->original_name }}<x-icon name="download" /></a>@endforeach</div></section>
    @endif
@endsection
