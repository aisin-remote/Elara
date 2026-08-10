<?php

namespace App\Http\Requests\Ai;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class AcceptTaskBreakdownRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accept', $this->route('breakdown'));
    }

    public function rules(): array
    {
        return [
            'tasks' => ['required', 'array', 'min:1', 'max:60'],
            'tasks.*.title' => ['required', 'string', 'max:200'],
            'tasks.*.description' => ['nullable', 'string', 'max:5000'],
            // Bounded on both sides: a zero-minute task is invisible to the planner, and a
            // task longer than two working weeks is a plan nobody can track.
            'tasks.*.estimate_minutes' => ['required', 'integer', 'min:15', 'max:4800'],
            'tasks.*.checklist' => ['nullable', 'array', 'max:30'],
            'tasks.*.checklist.*' => ['required', 'string', 'max:200'],
            'tasks.*.depends_on' => ['nullable', 'array', 'max:59'],
            'tasks.*.depends_on.*' => [
                'integer',
                'min:0',
                function (string $attribute, mixed $value, Closure $fail): void {
                    preg_match('/tasks\.(\d+)\.depends_on\.\d+/', $attribute, $matches);

                    if (! isset($matches[1]) || (int) $value >= (int) $matches[1]) {
                        $fail('A prerequisite must be an earlier task in this plan.');
                    }
                },
            ],
            'tasks.*.requires_user_validation' => ['nullable', 'boolean'],
            'tasks.*.validation_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'tasks.*.estimate_minutes.min' => 'Give every task at least 15 minutes, or remove it.',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function tasks(): array
    {
        return array_map(fn (array $task) => [
            'title' => $task['title'],
            'description' => $task['description'] ?? null,
            'estimate_minutes' => (int) $task['estimate_minutes'],
            'checklist' => array_values(array_unique($task['checklist'] ?? [])),
            'depends_on' => array_values(array_unique(array_map('intval', $task['depends_on'] ?? []))),
            'requires_user_validation' => (bool) ($task['requires_user_validation'] ?? false),
            'validation_reason' => $task['validation_reason'] ?? null,
        ], array_values($this->validated()['tasks']));
    }
}
