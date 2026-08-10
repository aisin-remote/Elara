@props(['label', 'name', 'checked' => false])
<label {{ $attributes->class(['flex min-h-11 items-center gap-3 text-sm']) }}><input type="checkbox" name="{{ $name }}" value="1" @checked($checked) class="rounded border-slate-300 text-orbit-600 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-900"><span>{{ $label }}</span></label>
