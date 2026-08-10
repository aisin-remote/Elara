@props(['variant' => 'info', 'title' => null, 'dismissible' => true, 'autoDismiss' => false])

@php
    $tone = match ($variant) {
        'success' => ['icon' => 'check', 'bar' => 'bg-emerald-500', 'chip' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', 'ring' => 'ring-emerald-500/25'],
        'error' => ['icon' => 'alert', 'bar' => 'bg-rose-500', 'chip' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400', 'ring' => 'ring-rose-500/25'],
        'warning' => ['icon' => 'alert', 'bar' => 'bg-amber-500', 'chip' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400', 'ring' => 'ring-amber-500/25'],
        default => ['icon' => 'info', 'bar' => 'bg-orbit-500', 'chip' => 'bg-orbit-500/10 text-orbit-600 dark:text-orbit-400', 'ring' => 'ring-orbit-500/25'],
    };
@endphp

<div
    x-data="{ show: true }"
    x-show="show"
    x-cloak
    @if ($autoDismiss) x-init="setTimeout(() => show = false, 7000)" @endif
    x-transition:enter="transition duration-200 ease-out"
    x-transition:enter-start="-translate-y-2 opacity-0"
    x-transition:leave="transition duration-150 ease-in"
    x-transition:leave-end="-translate-y-1 opacity-0"
    role="{{ $variant === 'error' ? 'alert' : 'status' }}"
    {{ $attributes->class(['relative flex max-w-2xl items-start gap-3 overflow-hidden rounded-2xl border border-slate-200 bg-white py-3.5 pl-5 pr-3.5 shadow-sm ring-1 dark:border-slate-800 dark:bg-slate-900', $tone['ring']]) }}
>
    <span class="absolute inset-y-0 left-0 w-1.5 {{ $tone['bar'] }}" aria-hidden="true"></span>
    <span class="grid size-9 shrink-0 place-items-center rounded-xl {{ $tone['chip'] }}"><x-icon :name="$tone['icon']" class="size-5" /></span>
    <div class="min-w-0 flex-1 pt-1">
        @if ($title)<p class="text-sm font-bold">{{ $title }}</p>@endif
        <div class="text-sm leading-6 text-slate-600 dark:text-slate-300 {{ $title ? 'mt-1' : '' }}">{{ $slot }}</div>
    </div>
    @if ($dismissible)
        <button type="button" x-on:click="show = false" class="grid size-8 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200" aria-label="Dismiss message"><x-icon name="close" /></button>
    @endif
</div>
