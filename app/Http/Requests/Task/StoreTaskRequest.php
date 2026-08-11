<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        ];
    }
}
