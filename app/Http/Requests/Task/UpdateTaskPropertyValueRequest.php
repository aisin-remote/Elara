<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPropertyType;
use App\Models\Task;
use App\Models\TaskProperty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskPropertyValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');
        $property = $this->route('property');

        return $task instanceof Task
            && $property instanceof TaskProperty
            && $task->project_id === $property->project_id
            && $this->user()->can('update', $task);
    }

    public function rules(): array
    {
        $property = $this->route('property');

        return ['value' => match ($property->type) {
            TaskPropertyType::TEXT => ['nullable', 'string', 'max:500'],
            TaskPropertyType::SELECT => ['nullable', 'string', Rule::in($property->options_json ?? [])],
            TaskPropertyType::CHECKBOX => ['required', 'boolean'],
        }];
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('property')?->type === TaskPropertyType::TEXT && is_string($this->input('value'))) {
            $this->merge(['value' => trim($this->input('value')) ?: null]);
        }
    }
}
