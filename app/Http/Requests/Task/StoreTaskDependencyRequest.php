<?php

namespace App\Http\Requests\Task;

use App\Enums\DependencyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskDependencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'dependency_public_id' => ['required', 'string', 'size:26'],
            'type' => ['nullable', Rule::enum(DependencyType::class)],
            'lag_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('type')) {
            $this->merge(['type' => DependencyType::FINISH_TO_START->value]);
        }

        if (! $this->filled('lag_minutes')) {
            $this->merge(['lag_minutes' => 0]);
        }
    }
}
