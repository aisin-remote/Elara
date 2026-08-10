<?php

namespace App\Http\Requests\File;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProjectFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('file'));
    }

    public function rules(): array
    {
        return [
            'original_name' => ['required', 'string', 'max:255', 'not_regex:/[[:cntrl:]\\\\\/]/'],
            'task_public_id' => ['nullable', 'string', 'size:26'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('task_public_id')) {
                return;
            }

            $file = $this->route('file');
            $task = Task::query()
                ->where('workspace_id', $file->workspace_id)
                ->where('public_id', $this->string('task_public_id'))
                ->first();

            if (! $task || ($file->project_id && $task->project_id !== $file->project_id) || ! $this->user()->can('update', $task)) {
                $validator->errors()->add('task_public_id', 'Choose an accessible task from this project.');
            }
        }];
    }
}
