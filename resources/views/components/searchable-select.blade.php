@props([
    'name',
    'options' => [],
    'selected' => null,
    'placeholder' => 'Choose one',
    'searchPlaceholder' => 'Type to search…',
    'emptyLabel' => null,
    'id' => null,
])

@php
    $fieldId = $id ?? $name;
    // Normalised here so the caller can pass models, arrays, or plain pairs without every
    // call site repeating the same map.
    $items = collect($options)->map(fn ($option) => [
        'value' => (string) (is_array($option) ? $option['value'] : $option->value),
        'label' => (string) (is_array($option) ? $option['label'] : $option->label),
    ])->values();
@endphp

{{-- Alpine rather than Select2: this project has no jQuery, and one dropdown is a poor reason
     to add a second runtime plus a stylesheet that has to be fought into matching the theme.
     The hidden input carries the value, so the form still submits correctly if the script
     never runs. --}}
<div
    x-data="{
        open: false,
        search: '',
        value: @js((string) $selected),
        items: @js($items),
        get filtered() {
            const needle = this.search.toLowerCase().trim();
            return needle === '' ? this.items : this.items.filter(i => i.label.toLowerCase().includes(needle));
        },
        get label() {
            return this.items.find(i => i.value === this.value)?.label ?? @js($emptyLabel ?? $placeholder);
        },
        choose(item) {
            this.value = item.value;
            this.open = false;
            this.search = '';
        },
        toggle() {
            this.open = ! this.open;
            if (this.open) this.$nextTick(() => this.$refs.search?.focus());
        },
    }"
    x-on:keydown.escape.stop="open = false"
    {{ $attributes->class('relative') }}
>
    <input type="hidden" name="{{ $name }}" x-model="value">

    <button type="button" id="{{ $fieldId }}" x-on:click="toggle()"
        x-bind:aria-expanded="open ? 'true' : 'false'" aria-haspopup="listbox"
        class="flex min-h-11 w-full items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 text-left text-sm dark:border-slate-700 dark:bg-slate-900">
        <span class="min-w-0 flex-1 truncate" x-bind:class="value === '' ? 'text-slate-400' : ''" x-text="label"></span>
        <x-icon name="chevron-right" class="size-4 shrink-0 text-slate-400 transition" ::class="open ? 'rotate-90' : ''" />
    </button>

    <div x-show="open" x-cloak x-on:click.outside="open = false"
        class="absolute z-30 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 p-2 dark:border-slate-800">
            <input type="text" x-ref="search" x-model="search" placeholder="{{ $searchPlaceholder }}"
                class="w-full rounded-lg border-slate-200 bg-transparent px-3 py-2 text-sm focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700"
                aria-label="{{ $searchPlaceholder }}">
        </div>

        <ul class="scrollbar-none max-h-56 overflow-y-auto py-1" role="listbox">
            @if ($emptyLabel)
                <li>
                    <button type="button" x-on:click="choose({ value: '', label: @js($emptyLabel) })"
                        class="flex w-full items-center px-3 py-2 text-left text-sm text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800">
                        {{ $emptyLabel }}
                    </button>
                </li>
            @endif

            <template x-for="item in filtered" x-bind:key="item.value">
                <li>
                    <button type="button" x-on:click="choose(item)" role="option"
                        x-bind:aria-selected="item.value === value ? 'true' : 'false'"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
                        x-bind:class="item.value === value ? 'bg-orbit-50 font-semibold text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : ''">
                        <span class="min-w-0 flex-1 truncate" x-text="item.label"></span>
                    </button>
                </li>
            </template>

            {{-- A search that finds nothing must say so, or it reads as a broken list. --}}
            <li x-show="filtered.length === 0" class="px-3 py-3 text-sm text-slate-500">
                Nothing matches “<span x-text="search"></span>”.
            </li>
        </ul>
    </div>
</div>
