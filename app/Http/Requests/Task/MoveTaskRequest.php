<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class MoveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'status_public_id' => ['required', 'string', 'size:26'],
            'before_task_public_id' => ['nullable', 'string', 'size:26', 'different:after_task_public_id'],
            'after_task_public_id' => ['nullable', 'string', 'size:26'],
            'version' => ['required', 'integer', 'min:1'],
            'operation_id' => ['required', 'uuid'],
        ];
    }
}
