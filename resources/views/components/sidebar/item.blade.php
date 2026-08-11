@props([
    'href',
    'icon' => null,
    'active' => false,
    'badge' => null,
])

<a href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'group flex h-8 items-center gap-2 rounded-md px-2 text-[13px] font-medium transition-colors',
        'bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' => $active,
        'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => ! $active,
    ]) }}>
    @if ($icon)
        <x-icon :name="$icon" @class([
            'size-4 transition-colors',
            'text-orbit-600 dark:text-orbit-300' => $active,
            'text-slate-500 group-hover:text-slate-700 dark:text-slate-400 dark:group-hover:text-slate-200' => ! $active,
        ]) />
    @endif

    <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>

    @if ($badge !== null && (int) $badge > 0)
        <span class="min-w-5 rounded-full bg-rose-500 px-1.5 text-center text-[10px] font-semibold leading-5 text-white">{{ $badge }}</span>
    @endif
</a>
