@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block min-h-11 w-full rounded-xl border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500']) }}>
