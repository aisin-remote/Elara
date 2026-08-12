<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInlineTaskFieldRequest extends FormRequest
{
    private const FIELDS = ['title', 'description', 'due_at', 'assignees', 'priority'];

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        $valueRules = match ($this->input('field')) {
            'title' => ['required', 'string', 'max:200'],
            'description' => ['present', 'nullable', 'string', 'max:10000'],
            'due_at' => ['present', 'nullable', 'date', function (string $attribute, mixed $value, \Closure $fail): void {
                $start = $this->route('task')?->start_at;

                if ($value && $start && $start->isAfter($value)) {
                    $fail('The due date must be after or equal to the start date.');
                }
            }],
            'assignees' => ['present', 'array', 'max:50'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            default => ['prohibited'],
        };

        return [
            'field' => ['required', 'string', Rule::in(self::FIELDS)],
            'value' => $valueRules,
            'value.*' => Rule::when($this->input('field') === 'assignees', ['string', 'size:26', 'distinct']),
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
