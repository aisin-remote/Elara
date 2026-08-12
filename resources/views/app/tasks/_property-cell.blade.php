@php
    $storedPropertyValue = $task->propertyValues->firstWhere('task_property_id', $property->id)?->value_json;
    $propertyValue = $property->type === App\Enums\TaskPropertyType::CHECKBOX
        ? (bool) $storedPropertyValue
        : $storedPropertyValue;
@endphp

<td class="min-w-44 px-2 py-2">
    @if ($canEditTask)
        <div
            x-data="inlineTaskProperty({
                url: @js(route('internal.task-properties.values.update', [$task, $property])),
                initial: @js($propertyValue),
                type: @js($property->type->value),
                reloadOnSave: @js($groupBy === 'property:'.$property->public_id),
            })"
            class="relative">
            @if ($property->type === App\Enums\TaskPropertyType::TEXT)
                <input
                    type="text"
                    x-model="value"
                    x-on:keydown.enter.prevent="$el.blur()"
                    x-on:blur="save()"
                    maxlength="500"
                    class="h-9 w-full rounded-lg border-transparent bg-transparent px-2 text-sm hover:border-slate-300 focus:border-orbit-500 focus:ring-orbit-500 dark:hover:border-slate-600"
                    aria-label="{{ $property->name }} for {{ $task->title }}"
                    placeholder="Empty">
            @elseif ($property->type === App\Enums\TaskPropertyType::SELECT)
                <select
                    x-model="value"
                    x-on:change="save()"
                    class="h-9 w-full rounded-lg border-transparent bg-transparent py-1 pl-2 pr-8 text-sm hover:border-slate-300 focus:border-orbit-500 focus:ring-orbit-500 dark:bg-slate-900 dark:hover:border-slate-600"
                    aria-label="{{ $property->name }} for {{ $task->title }}">
                    <option value="">Empty</option>
                    @foreach ($property->options_json ?? [] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            @else
                <label class="inline-flex min-h-9 cursor-pointer items-center gap-2 rounded-lg px-2 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <input type="checkbox" x-model="value" x-on:change="save()" class="rounded border-slate-300 text-orbit-600 focus:ring-orbit-500">
                    <span class="text-xs text-slate-500" x-text="value ? 'Checked' : 'Unchecked'"></span>
                </label>
            @endif
            <span x-cloak x-show="saving" class="absolute right-2 top-1/2 size-3 -translate-y-1/2 animate-spin rounded-full border-2 border-slate-200 border-t-orbit-500 dark:border-slate-700 dark:border-t-orbit-400" aria-label="Saving"></span>
        </div>
    @elseif ($property->type === App\Enums\TaskPropertyType::CHECKBOX)
        <span class="inline-flex items-center gap-2 text-sm text-slate-500">
            <span class="grid size-5 place-items-center rounded border {{ $propertyValue ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 dark:border-slate-600' }}">{{ $propertyValue ? '✓' : '' }}</span>
            {{ $propertyValue ? 'Checked' : 'Unchecked' }}
        </span>
    @else
        <span class="block max-w-48 truncate text-sm text-slate-500">{{ filled($propertyValue) ? $propertyValue : '—' }}</span>
    @endif
</td>
