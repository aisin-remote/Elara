@props(['label' => 'Loading'])
<span role="status" aria-label="{{ $label }}" {{ $attributes->class(['block animate-pulse rounded-lg bg-slate-200 dark:bg-slate-800']) }}><span class="sr-only">{{ $label }}</span></span>
