@props(['label' => 'Section navigation'])
<nav aria-label="{{ $label }}" {{ $attributes->class(['flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-800']) }}>{{ $slot }}</nav>
