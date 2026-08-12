<form method="POST" action="{{ route('internal.tasks.store', $project) }}" class="space-y-5">
    @csrf
    <div>
        <x-label for="new-task-status">Group</x-label>
        <x-select id="new-task-status" name="status_public_id" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->public_id }}" @selected(old('status_public_id', $defaultStatus?->public_id) === $status->public_id)>{{ $status->name }}</option>
            @endforeach
        </x-select>
        <x-field-error name="status_public_id" />
    </div>

    @if (request('feature'))
        <input type="hidden" name="feature_public_id" value="{{ request('feature') }}">
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($taskFields as $field)
            @if ($field['kind'] === 'system' && $field['key'] === 'title')
                <div class="sm:col-span-2">
                    <x-label for="new-task-title">{{ $field['name'] }}</x-label>
                    <x-input id="new-task-title" name="title" value="{{ old('title') }}" required maxlength="200" />
                    <x-field-error name="title" />
                </div>
            @elseif ($field['kind'] === 'system' && $field['key'] === 'description')
                <div class="sm:col-span-2">
                    <x-label for="new-task-description">{{ $field['name'] }}</x-label>
                    <x-textarea id="new-task-description" name="description" rows="4">{{ old('description') }}</x-textarea>
                    <x-field-error name="description" />
                </div>
            @elseif ($field['kind'] === 'system' && $field['key'] === 'due_at')
                <div>
                    <x-label for="new-task-due">{{ $field['name'] }}</x-label>
                    <x-input id="new-task-due" type="datetime-local" name="due_at" value="{{ old('due_at') }}" />
                    <x-field-error name="due_at" />
                </div>
            @elseif ($field['kind'] === 'system' && $field['key'] === 'priority')
                <div>
                    <x-label for="new-task-priority">{{ $field['name'] }}</x-label>
                    <x-select id="new-task-priority" name="priority">
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(old('priority', 'medium') === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </x-select>
                    <x-field-error name="priority" />
                </div>
            @elseif ($field['kind'] === 'system' && $field['key'] === 'assignees')
                <fieldset class="sm:col-span-2">
                    <legend class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $field['name'] }}</legend>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($projectMembers as $membership)
                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-700">
                                <input type="checkbox" name="assignee_public_ids[]" value="{{ $membership->user->public_id }}" class="rounded border-slate-300 text-orbit-600 focus:ring-orbit-500" @checked(in_array($membership->user->public_id, old('assignee_public_ids', [])))>
                                <span>{{ $membership->user->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-field-error name="assignee_public_ids" />
                </fieldset>
            @elseif ($field['kind'] === 'custom')
                @php
                    $property = $field['property'];
                @endphp
                <div class="{{ $property->type === App\Enums\TaskPropertyType::TEXT ? 'sm:col-span-2' : '' }}">
                    <x-label for="new-task-property-{{ $property->public_id }}">{{ $property->name }}</x-label>
                    @if ($property->type === App\Enums\TaskPropertyType::TEXT)
                        <x-input id="new-task-property-{{ $property->public_id }}" name="property_values[{{ $property->public_id }}]" value="{{ old('property_values.'.$property->public_id) }}" maxlength="500" />
                    @elseif ($property->type === App\Enums\TaskPropertyType::SELECT)
                        <x-select id="new-task-property-{{ $property->public_id }}" name="property_values[{{ $property->public_id }}]">
                            <option value="">No selection</option>
                            @foreach ($property->options_json ?? [] as $option)
                                <option value="{{ $option }}" @selected(old('property_values.'.$property->public_id) === $option)>{{ $option }}</option>
                            @endforeach
                        </x-select>
                    @else
                        <input type="hidden" name="property_values[{{ $property->public_id }}]" value="0">
                        <label class="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-3 text-sm dark:border-slate-700">
                            <input id="new-task-property-{{ $property->public_id }}" type="checkbox" name="property_values[{{ $property->public_id }}]" value="1" class="rounded border-slate-300 text-orbit-600 focus:ring-orbit-500" @checked(old('property_values.'.$property->public_id))>
                            <span>Completed</span>
                        </label>
                    @endif
                    <x-field-error name="property_values.{{ $property->public_id }}" />
                </div>
            @endif
        @endforeach
    </div>

    <div class="flex justify-end"><x-button>Create task</x-button></div>
</form>
