@php
    $revisions = $meetingMinute->revisions;
@endphp

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h3 class="text-lg font-bold">Revision history</h3>
            <p class="mt-1 text-sm text-slate-500">Open a version to see the MOM exactly as it was recorded.</p>
        </div>
        <span class="shrink-0 text-sm font-bold text-slate-500">{{ $revisions->count() }} versions</span>
    </div>

    <ol class="mt-4 max-h-96 divide-y divide-slate-100 overflow-y-auto pr-1 dark:divide-slate-800">
        @forelse($revisions as $revision)
            <li>
                <button type="button" onclick="document.getElementById('mom-revision-{{ $revision->public_id }}').showModal()" class="flex w-full items-center justify-between gap-4 py-3 text-left text-sm transition hover:text-orbit-700 dark:hover:text-orbit-300" aria-label="View MOM version {{ $revision->revision }}">
                    <span class="min-w-0">
                        <strong>Version {{ $revision->revision }}</strong>
                        <span class="text-slate-500"> · {{ $revision->editor?->name ?? 'Deleted user' }}</span>
                    </span>
                    <span class="flex shrink-0 items-center gap-3">
                        <time class="hidden text-xs text-slate-500 sm:inline">{{ $revision->created_at->diffForHumans() }}</time>
                        <span class="text-xs font-bold">View</span>
                        <x-icon name="chevron-right" class="size-4" />
                    </span>
                </button>
            </li>
        @empty
            <li class="py-3 text-sm text-slate-500">No revisions recorded yet.</li>
        @endforelse
    </ol>
</section>

@foreach($revisions as $revision)
    @php
        $snapshot = $revision->snapshot_json ?? [];
        $snapshotItems = collect(data_get($snapshot, 'items', []));
        $snapshotMeetingAt = filled(data_get($snapshot, 'meeting_at')) ? Carbon\CarbonImmutable::parse(data_get($snapshot, 'meeting_at')) : null;
        $snapshotLifecycle = App\Enums\MeetingMinutePublicationStatus::tryFrom((string) data_get($snapshot, 'publication_status'));
        $snapshotProject = data_get($snapshot, 'project_name');
        if (! $snapshotProject) {
            $snapshotProject = blank(data_get($snapshot, 'project_id'))
                ? 'General'
                : ((int) data_get($snapshot, 'project_id') === (int) $meetingMinute->project_id
                    ? ($meetingMinute->project?->name ?? 'Archived project / system')
                    : 'Previous project / system');
        }
    @endphp

    <x-modal id="mom-revision-{{ $revision->public_id }}" :title="'MOM version '.$revision->revision" class="max-h-[90vh] w-[min(94vw,820px)] max-w-none overflow-y-auto">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-bold">{{ $revision->editor?->name ?? 'Deleted user' }}</p>
                <p class="mt-1 text-xs text-slate-500">Saved {{ $revision->created_at->format('M j, Y · H:i') }}</p>
            </div>
            <x-badge :tone="match($snapshotLifecycle) { App\Enums\MeetingMinutePublicationStatus::DRAFT => 'warning', App\Enums\MeetingMinutePublicationStatus::LOCKED => 'slate', default => 'success' }">{{ $snapshotLifecycle?->label() ?? 'Unknown' }}</x-badge>
        </div>

        <dl class="mt-5 grid gap-4 rounded-2xl bg-slate-50 p-4 sm:grid-cols-2 dark:bg-slate-950/60">
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Title</dt><dd class="mt-1 font-semibold">{{ data_get($snapshot, 'title', 'Untitled MOM') }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Meeting date</dt><dd class="mt-1 font-semibold">{{ $snapshotMeetingAt?->format('M j, Y · H:i') ?? 'Not recorded' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Project / system</dt><dd class="mt-1 font-semibold">{{ $snapshotProject }}</dd></div>
        </dl>

        <div class="mt-5">
            <h4 class="font-bold">Summary</h4>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-slate-300">{{ data_get($snapshot, 'summary') ?: 'No summary was recorded in this version.' }}</p>
        </div>

        <div class="mt-5">
            <h4 class="font-bold">Action items</h4>
            <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-950/60"><tr><th class="px-4 py-3">Action / decision</th><th class="px-4 py-3">PIC</th><th class="px-4 py-3">Due date</th><th class="px-4 py-3">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($snapshotItems as $item)
                            @php($itemStatus = App\Enums\MeetingMinuteStatus::tryFrom((string) data_get($item, 'status')))
                            <tr class="align-top"><td class="max-w-md whitespace-pre-line px-4 py-3 font-semibold">{{ data_get($item, 'content') }}</td><td class="px-4 py-3">{{ data_get($item, 'pic_name') ?: 'Unassigned' }}</td><td class="whitespace-nowrap px-4 py-3">{{ filled(data_get($item, 'due_date')) ? Carbon\CarbonImmutable::parse(data_get($item, 'due_date'))->format('M j, Y') : 'TBA' }}</td><td class="whitespace-nowrap px-4 py-3 font-semibold">{{ $itemStatus?->label() ?? 'Unknown' }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No action items were recorded in this version.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-4 text-xs text-slate-500">Documents are not included in revision snapshots.</p>
    </x-modal>
@endforeach
