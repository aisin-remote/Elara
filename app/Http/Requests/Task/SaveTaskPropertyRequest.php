<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPropertyType;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskProperty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTaskPropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $property = $this->route('property');

        if ($property instanceof TaskProperty) {
            $project = $property->project;
        }

        return $project instanceof Project
            && $this->user()->can('manageWorkflow', [Task::class, $project]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::enum(TaskPropertyType::class)],
            'options_text' => ['nullable', 'string', 'max:2000'],
            'options' => ['array', 'max:25'],
            'options.*' => ['string', 'max:80', 'distinct:strict'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rawOptions = $this->has('options_text')
            ? preg_split('/\r\n|\r|\n/', (string) $this->input('options_text'))
            : (array) $this->input('options', []);
        $options = collect($rawOptions)
            ->map(fn (string $option) => trim($option))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'options' => $options,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->string('type')->toString() === TaskPropertyType::SELECT->value
                && $this->collect('options')->isEmpty()) {
                $validator->errors()->add('options_text', 'Add at least one select option.');
            }
        });
    }
}
