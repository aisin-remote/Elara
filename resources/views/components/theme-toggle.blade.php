{{-- One definition for all four layouts. Four copies of a control this small is how two of
     them end up behaving differently a month later. --}}
<button type="button" x-on:click="toggle()"
    {{ $attributes->class('inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-300 px-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800') }}
    x-bind:aria-pressed="isDark ? 'true' : 'false'"
    x-bind:aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'">
    {{-- Both icons rendered, one shown: swapping innerHTML on click makes the control flicker. --}}
    <span x-show="! isDark" x-cloak><x-icon name="sun" class="size-4" /></span>
    <span x-show="isDark" x-cloak><x-icon name="moon" class="size-4" /></span>
    <span x-text="isDark ? 'Dark' : 'Light'">Light</span>
</button>
