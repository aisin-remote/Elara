<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Task::class, $this->route('project')]);
    }

    public function rules(): array
    {
        return [
            'task_public_ids' => ['required', 'array', 'min:1', 'max:100'],
            'task_public_ids.*' => ['string', 'size:26', 'distinct'],
            'action' => ['required', Rule::in(['status', 'priority', 'assignee', 'archive'])],
            'status_public_id' => ['required_if:action,status', 'nullable', 'string', 'size:26'],
            'priority' => ['required_if:action,priority', 'nullable', Rule::enum(TaskPriority::class)],
            'assignee_public_id' => ['required_if:action,assignee', 'nullable', 'string', 'size:26'],
        ];
    }
}
