<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskPropertyType;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Task::class, $this->route('project')]);
    }

    public function rules(): array
    {
        return self::taskRules();
    }

    public static function taskRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status_public_id' => ['required', 'string', 'size:26'],
            'category_public_id' => ['nullable', 'string', 'size:26'],
            'feature_public_id' => ['nullable', 'string', 'size:26'],
            'milestone_public_id' => ['nullable', 'string', 'size:26'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'estimate_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'assignee_public_ids' => ['nullable', 'array', 'max:50'],
            'assignee_public_ids.*' => ['string', 'size:26', 'distinct'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip'],
            'property_values' => ['nullable', 'array'],
            'property_values.*' => ['nullable'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'priority' => $this->input('priority', TaskPriority::MEDIUM->value),
            'assignee_public_ids' => $this->input('assignee_public_ids', []),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $project = $this->route('project');

            if (! $project instanceof Project) {
                return;
            }

            $submitted = (array) $this->input('property_values', []);
            $properties = $project->taskProperties()
                ->active()
                ->whereIn('public_id', array_keys($submitted))
                ->get()
                ->keyBy('public_id');

            foreach ($submitted as $publicId => $value) {
                $property = $properties->get($publicId);
                $attribute = 'property_values.'.$publicId;

                if ($property === null) {
                    $validator->errors()->add($attribute, 'Choose a property from this project.');

                    continue;
                }

                if ($value === '' || $value === null) {
                    continue;
                }

                match ($property->type) {
                    TaskPropertyType::TEXT => ! is_string($value) || mb_strlen($value) > 500
                        ? $validator->errors()->add($attribute, 'Enter no more than 500 characters.')
                        : null,
                    TaskPropertyType::SELECT => ! is_string($value) || ! in_array($value, $property->options_json ?? [], true)
                        ? $validator->errors()->add($attribute, 'Choose one of the available options.')
                        : null,
                    TaskPropertyType::CHECKBOX => ! in_array($value, [true, false, 1, 0, '1', '0'], true)
                        ? $validator->errors()->add($attribute, 'The checklist value must be true or false.')
                        : null,
                };
            }
        });
    }
}
