@props(['value' => null])

<label {{ $attributes->merge(['class' => 'mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200']) }}>
    {{ $value ?? $slot }}
</label>
