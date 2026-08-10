@props(['text'])
<span {{ $attributes->class(['group relative inline-flex']) }}>{{ $slot }}<span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-slate-950 px-2.5 py-1.5 text-xs text-white group-hover:block group-focus-within:block">{{ $text }}</span></span>
