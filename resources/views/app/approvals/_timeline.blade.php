@php($visible = 5)

<section
    @if ($timeline->count() > $visible) x-data="{ all: false }" @endif
    class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
    aria-labelledby="history-title">
    <div class="flex items-baseline justify-between gap-3">
        <h3 id="history-title" class="font-bold">History</h3>
        @if ($timeline->count() > $visible)
            <span class="text-xs text-slate-400">{{ $timeline->count() }} entries</span>
        @endif
    </div>

    <ol class="mt-4 space-y-4">
        @foreach ($timeline as $index => $entry)
            {{-- Everything is rendered; the tail is only hidden. A history that hides rows
                 from the DOM is one nobody can search with ctrl+F. --}}
            <li class="grid grid-cols-[10px_1fr] gap-3 text-sm"
                @if ($index >= $visible) x-show="all" x-cloak @endif>
                <span class="mt-1.5 size-2 rounded-full bg-orbit-500 ring-4 ring-orbit-50 dark:ring-orbit-950"></span>
                <div>
                    <p><span class="font-semibold">{{ $entry->actor?->name ?? 'System' }}</span>
                        <span class="text-slate-600 dark:text-slate-300">{{ str($entry->action)->after('.')->replace('_', ' ') }}</span></p>
                    @if ($note = data_get($entry->metadata_json, 'note'))
                        <p class="mt-1 text-xs text-slate-500">{{ $note }}</p>
                    @endif
                    <time datetime="{{ $entry->created_at->toIso8601String() }}" class="mt-1 block text-xs text-slate-400">{{ $entry->created_at->diffForHumans() }}</time>
                </div>
            </li>
        @endforeach
    </ol>

    @if ($timeline->count() > $visible)
        <button type="button" @click="all = ! all" :aria-expanded="all ? 'true' : 'false'"
            class="mt-4 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-500 transition hover:bg-slate-50 hover:text-slate-900 dark:border-slate-800 dark:hover:bg-slate-800 dark:hover:text-white"
            x-text="all ? 'Show less' : 'Show all {{ $timeline->count() }} entries'">Show all {{ $timeline->count() }} entries</button>
    @endif
</section>
