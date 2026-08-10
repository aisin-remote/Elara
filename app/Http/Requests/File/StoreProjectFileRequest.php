<?php

namespace App\Http\Requests\File;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProjectFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [ProjectFile::class, $this->route('workspace')]);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.config('orbitra.max_file_upload_kb'), 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip'],
            'project_public_id' => ['nullable', 'string', 'size:26'],
            'task_public_id' => ['nullable', 'string', 'size:26'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $workspace = $this->route('workspace');
            $project = $this->filled('project_public_id')
                ? Project::query()->where('workspace_id', $workspace->id)->where('public_id', $this->string('project_public_id'))->first()
                : null;
            $task = $this->filled('task_public_id')
                ? Task::query()->where('workspace_id', $workspace->id)->where('public_id', $this->string('task_public_id'))->first()
                : null;

            if ($this->filled('project_public_id') && (! $project || ! $this->user()->can('create', [Task::class, $project]))) {
                $validator->errors()->add('project_public_id', 'Choose a project you can update.');
            }
            if ($this->filled('task_public_id') && (! $task || ! $this->user()->can('update', $task))) {
                $validator->errors()->add('task_public_id', 'Choose a task you can update.');
            }
            if ($project && $task && $task->project_id !== $project->id) {
                $validator->errors()->add('task_public_id', 'The task must belong to the selected project.');
            }
        }];
    }
}
