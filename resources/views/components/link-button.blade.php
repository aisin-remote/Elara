@props(['variant' => 'primary'])

@php
    $classes = match ($variant) {
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800',
        default => 'bg-slate-950 text-white hover:bg-slate-800 dark:bg-orbit-500 dark:text-slate-950 dark:hover:bg-orbit-400',
    };
@endphp

<a {{ $attributes->merge(['class' => "inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition {$classes}"]) }}>
    {{ $slot }}
</a>
