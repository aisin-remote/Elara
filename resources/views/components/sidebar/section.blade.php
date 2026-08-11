@props([
    'id',
    'title',
    'actionHref' => null,
    'actionLabel' => null,
])

<section class="mt-3">
    <div class="group flex h-7 items-center gap-1 px-1">
        <button type="button"
            class="flex min-w-0 flex-1 items-center gap-1 rounded px-1 py-1 text-left text-[11px] font-medium text-slate-500 hover:text-orbit-700 dark:text-slate-400 dark:hover:text-orbit-300"
            x-on:click="toggleSidebarSection(@js($id))"
            x-bind:aria-expanded="sidebarSectionOpen(@js($id))">
            <x-icon name="chevron-right" class="size-3 transition-transform duration-150" x-bind:class="sidebarSectionOpen(@js($id)) ? 'rotate-90' : ''" />
            <span class="truncate">{{ $title }}</span>
        </button>

        @if ($actionHref)
            <a href="{{ $actionHref }}"
                class="grid size-6 shrink-0 place-items-center rounded text-slate-400 opacity-0 transition hover:bg-slate-100 hover:text-orbit-700 focus:opacity-100 group-hover:opacity-100 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-orbit-300"
                aria-label="{{ $actionLabel ?? 'Open '.$title }}">
                <x-icon name="plus" class="size-3.5" />
            </a>
        @endif
    </div>

    <div x-cloak x-show="sidebarSectionOpen(@js($id))" class="mt-0.5 space-y-0.5">
        {{ $slot }}
    </div>
</section>
