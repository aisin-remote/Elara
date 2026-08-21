<section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="related-minutes-title">
    <div class="flex items-center justify-between gap-3"><div><h2 id="related-minutes-title" class="text-lg font-bold">Related MOM</h2><p class="mt-1 text-sm text-slate-500">Decisions and follow-ups linked to this {{ $subjectLabel }}.</p></div><a href="{{ route('app.schedule.minutes.index', $workspace) }}" class="text-sm font-bold text-orbit-700 dark:text-orbit-300">Open MOM</a></div>
    <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
        @forelse ($relatedMinutes as $minute)
            <a href="{{ route('app.schedule.minutes.show', [$workspace, $minute]) }}" class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                <div><p class="text-sm font-bold hover:text-orbit-700 dark:hover:text-orbit-300">{{ $minute->title }}</p><p class="mt-1 text-xs text-slate-500">{{ $minute->meeting_at->format('M j, Y · H:i') }} · {{ $minute->related_items_count }} action {{ Str::plural('item', $minute->related_items_count) }}</p></div><x-icon name="chevron-right" class="size-4 text-slate-400" />
            </a>
        @empty
            <p class="py-3 text-sm text-slate-500">No meeting minutes linked yet.</p>
        @endforelse
    </div>
</section>
