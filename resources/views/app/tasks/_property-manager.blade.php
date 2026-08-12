<div x-show="propertyPanel === 'new'" x-data="{ type: 'text' }">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h4 class="text-sm font-bold">Add property</h4>
            <p class="mt-1 text-xs text-slate-500">This column will appear in every group.</p>
        </div>
        <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white" x-on:click="closeProperty()" aria-label="Close property editor">
            <x-icon name="close" class="size-4" />
        </button>
    </div>

    <form method="POST" action="{{ route('internal.task-properties.store', $project) }}" class="mt-4 space-y-3">
        @csrf
        <div>
            <x-label for="new-property-name-{{ $editorKey }}">Property name</x-label>
            <x-input id="new-property-name-{{ $editorKey }}" name="name" placeholder="e.g. Bug type" required maxlength="80" />
        </div>
        <div>
            <x-label for="new-property-type-{{ $editorKey }}">Type</x-label>
            <x-select id="new-property-type-{{ $editorKey }}" name="type" x-model="type">
                @foreach (App\Enums\TaskPropertyType::cases() as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </x-select>
        </div>
        <div x-show="type === 'select'">
            <x-label for="new-property-options-{{ $editorKey }}">Select options</x-label>
            <textarea id="new-property-options-{{ $editorKey }}" name="options_text" rows="2" class="w-full rounded-xl border-slate-300 bg-white text-sm focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-950" placeholder="One option per line"></textarea>
        </div>
        <x-button class="w-full"><x-icon name="plus" />Add property</x-button>
    </form>

    @if ($systemFields->where('visible', false)->isNotEmpty())
        <div class="mt-4 border-t border-slate-200 pt-3 dark:border-slate-700">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Hidden system properties</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($systemFields->where('visible', false) as $hiddenField)
                    <form method="POST" action="{{ route('internal.task-fields.update', [$project, $hiddenField['key']]) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="name" value="{{ $hiddenField['name'] }}">
                        <input type="hidden" name="visible" value="1">
                        <x-button variant="secondary"><x-icon name="plus" />{{ $hiddenField['name'] }}</x-button>
                    </form>
                @endforeach
            </div>
        </div>
    @endif
</div>

@foreach ($systemFields as $systemField)
    <div x-show="propertyPanel === @js('system:'.$systemField['key'])">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h4 class="text-sm font-bold">Edit {{ $systemField['name'] }}</h4>
                <p class="mt-1 text-xs text-slate-500">System property · {{ match ($systemField['type']) { 'people' => 'People', 'date' => 'Date', 'select' => 'Select', default => 'Text' } }}. Its type stays fixed because reports and automation use this data.</p>
            </div>
            <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white" x-on:click="closeProperty()" aria-label="Close property editor">
                <x-icon name="close" class="size-4" />
            </button>
        </div>

        <div class="mt-4 space-y-3">
            <form method="POST" action="{{ route('internal.task-fields.update', [$project, $systemField['key']]) }}" class="space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="visible" value="1">
                <div class="flex-1">
                    <x-label for="system-field-name-{{ $editorKey }}-{{ $systemField['key'] }}">Property name</x-label>
                    <x-input id="system-field-name-{{ $editorKey }}-{{ $systemField['key'] }}" name="name" value="{{ $systemField['name'] }}" required maxlength="80" />
                </div>
                <x-button variant="secondary" class="w-full">Save property</x-button>
            </form>

            @if ($systemField['hideable'])
                <form method="POST" action="{{ route('internal.task-fields.update', [$project, $systemField['key']]) }}" onsubmit="return confirm('Hide this property from the table and Add task form? Existing data will stay safe.')">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="name" value="{{ $systemField['name'] }}">
                    <input type="hidden" name="visible" value="0">
                    <x-button variant="danger" class="w-full">Hide property</x-button>
                </form>
            @else
                <p class="text-xs text-slate-500">Required property</p>
            @endif
        </div>
    </div>
@endforeach

@foreach ($properties as $property)
    <div x-show="propertyPanel === @js($property->public_id)" x-data="{ type: @js($property->type->value) }">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h4 class="text-sm font-bold">Edit {{ $property->name }}</h4>
                <p class="mt-1 text-xs text-slate-500">Changing the type clears existing values in this column.</p>
            </div>
            <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white" x-on:click="closeProperty()" aria-label="Close property editor">
                <x-icon name="close" class="size-4" />
            </button>
        </div>

        <div class="mt-4 space-y-3">
            <form method="POST" action="{{ route('internal.task-properties.update', $property) }}" class="space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <x-label for="property-name-{{ $editorKey }}-{{ $property->public_id }}">Property name</x-label>
                    <x-input id="property-name-{{ $editorKey }}-{{ $property->public_id }}" name="name" value="{{ $property->name }}" required maxlength="80" />
                </div>
                <div>
                    <x-label for="property-type-{{ $editorKey }}-{{ $property->public_id }}">Type</x-label>
                    <x-select id="property-type-{{ $editorKey }}-{{ $property->public_id }}" name="type" x-model="type">
                        @foreach (App\Enums\TaskPropertyType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div x-show="type === 'select'">
                    <x-label for="property-options-{{ $editorKey }}-{{ $property->public_id }}">Select options</x-label>
                    <textarea id="property-options-{{ $editorKey }}-{{ $property->public_id }}" name="options_text" rows="2" class="w-full rounded-xl border-slate-300 bg-white text-sm focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-950" placeholder="One option per line">{{ implode("\n", $property->options_json ?? []) }}</textarea>
                </div>
                <x-button variant="secondary" class="w-full">Save property</x-button>
            </form>

            <form method="POST" action="{{ route('internal.task-properties.destroy', $property) }}" onsubmit="return confirm('Delete this property? Its values will be hidden.')">
                @csrf
                @method('DELETE')
                <x-button variant="danger" class="w-full">Delete property</x-button>
            </form>
        </div>
    </div>
@endforeach
