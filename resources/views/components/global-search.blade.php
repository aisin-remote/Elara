@props(['workspace'])

<div
    x-data="globalSearch({ endpoint: @js(route('app.search', $workspace)) })"
    x-on:open-global-search.window="open()"
    x-on:keydown.ctrl.k.window.prevent="open()"
    x-on:keydown.meta.k.window.prevent="open()">
    <dialog
        x-ref="dialog"
        x-on:close="reset()"
        x-on:click="if ($event.target === $refs.dialog) close()"
        data-global-search-dialog
        class="m-auto w-[min(94vw,640px)] overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 text-slate-950 shadow-2xl backdrop:bg-slate-950/65 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
        <div class="flex items-center gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
            <x-icon name="search" class="size-5 text-orbit-600 dark:text-orbit-300" />
            <label for="global-search-input" class="sr-only">Search workspace</label>
            <input
                id="global-search-input"
                x-ref="input"
                x-model="query"
                x-on:input.debounce.250ms="search()"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent="openSelected()"
                type="search"
                autocomplete="off"
                spellcheck="false"
                placeholder="Search all accessible work..."
                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-base font-medium placeholder:text-slate-400 focus:ring-0 dark:placeholder:text-slate-500">
            <button type="button" x-on:click="close()" class="rounded-lg border border-slate-200 px-2 py-1 text-[10px] font-bold text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close global search">ESC</button>
        </div>

        <div x-ref="results" class="max-h-[min(65vh,520px)] min-h-32 overflow-y-auto p-2" aria-live="polite">
            <div x-show="query.trim().length === 0" data-search-quick-routes class="p-2">
                <p class="px-2 pb-2 text-xs font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">Quick access</p>
                <div class="grid gap-1 sm:grid-cols-2">
                    @foreach ([
                        ['label' => 'Home', 'description' => 'Workspace dashboard', 'icon' => 'dashboard', 'url' => route('app.workspaces.show', $workspace)],
                        ['label' => 'My tasks', 'description' => 'Personal and assigned work', 'icon' => 'tasks', 'url' => route('app.tasks.index', $workspace)],
                        ['label' => 'All projects', 'description' => 'Browse delivery projects', 'icon' => 'projects', 'url' => route('app.projects.index', $workspace)],
                        ['label' => 'Features', 'description' => 'Systems and feature work', 'icon' => 'board', 'url' => route('app.features.index', $workspace)],
                        ['label' => 'Supporting', 'description' => 'Independent support work', 'icon' => 'supporting', 'url' => route('app.supporting.index', $workspace)],
                        ['label' => 'Schedule', 'description' => 'Meetings and planned activity', 'icon' => 'calendar', 'url' => route('app.schedule.index', $workspace)],
                    ] as $quickRoute)
                        <a href="{{ $quickRoute['url'] }}" class="group flex items-center gap-3 rounded-xl px-3 py-3 text-slate-700 transition-colors hover:bg-orbit-50 hover:text-orbit-900 focus-visible:bg-orbit-50 dark:text-slate-200 dark:hover:bg-orbit-950/60 dark:hover:text-orbit-100 dark:focus-visible:bg-orbit-950/60">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500 transition-colors group-hover:bg-white group-hover:text-orbit-600 dark:bg-slate-800 dark:text-slate-400 dark:group-hover:bg-slate-900 dark:group-hover:text-orbit-300">
                                <x-icon :name="$quickRoute['icon']" class="size-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold">{{ $quickRoute['label'] }}</span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">{{ $quickRoute['description'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div x-cloak x-show="query.trim().length === 1 && ! loading" class="grid min-h-28 place-items-center px-6 text-center text-sm text-slate-500 dark:text-slate-400">
                Type one more character to search this workspace.
            </div>

            <div x-cloak x-show="loading" class="flex min-h-28 items-center justify-center gap-3 text-sm font-medium text-slate-500 dark:text-slate-400">
                <span class="size-5 animate-spin rounded-full border-2 border-slate-200 border-t-orbit-500 dark:border-slate-700 dark:border-t-orbit-400"></span>
                Searching...
            </div>

            <div x-cloak x-show="error && ! loading" class="grid min-h-28 place-items-center px-6 text-center text-sm text-rose-600 dark:text-rose-400" x-text="error"></div>

            <div x-cloak x-show="searched && ! loading && ! error && results.length === 0" class="grid min-h-28 place-items-center px-6 text-center">
                <div>
                    <p class="font-semibold">No results found</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Try a project, feature, request, task, supporting item, file, member, or conversation.</p>
                </div>
            </div>

            <template x-for="(result, index) in results" :key="result.type + '|' + result.url">
                <a
                    :href="result.url"
                    :data-search-index="index"
                    x-on:mouseenter="selected = index"
                    :class="selected === index ? 'bg-orbit-50 text-orbit-900 dark:bg-orbit-950/60 dark:text-orbit-100' : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800'"
                    class="group flex items-center gap-3 rounded-xl px-3 py-3 transition-colors">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500 group-hover:text-orbit-600 dark:bg-slate-800 dark:text-slate-400 dark:group-hover:text-orbit-300">
                        <x-icon name="search" class="size-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold" x-text="result.label"></span>
                        <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400" x-text="result.description"></span>
                    </span>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400" x-text="result.type"></span>
                    <x-icon name="chevron-right" class="size-4 shrink-0 text-slate-300 dark:text-slate-600" />
                </a>
            </template>
        </div>
    </dialog>
</div>
