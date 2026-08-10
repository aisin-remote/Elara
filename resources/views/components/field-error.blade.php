@props(['name'])

@error($name)
    <p class="mt-2 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
@enderror
