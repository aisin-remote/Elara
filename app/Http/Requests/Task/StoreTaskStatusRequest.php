<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskStatusCategory;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageWorkflow', [Task::class, $this->route('project')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'category' => ['required', Rule::enum(TaskStatusCategory::class)],
        ];
    }
}
