<?php

namespace App\Http\Requests\Task;

use App\Models\Project;
use App\Models\Task;
use App\Services\TaskFieldSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user()->can('manageWorkflow', [Task::class, $project]);
    }

    public function rules(): array
    {
        return [
            'field' => ['required', Rule::in(array_keys(TaskFieldSchema::SYSTEM_FIELDS))],
            'name' => ['required', 'string', 'max:80'],
            'visible' => ['present', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'field' => $this->route('field'),
            'name' => trim((string) $this->input('name')),
            'visible' => $this->boolean('visible'),
        ]);
    }
}
