@props(['type' => 'submit', 'variant' => 'primary'])

@php
    $classes = match ($variant) {
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700',
        default => 'bg-slate-950 text-white hover:bg-slate-800 dark:bg-orbit-500 dark:text-slate-950 dark:hover:bg-orbit-400',
    };
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => "inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 {$classes}"]) }}>
    {{ $slot }}
</button>
