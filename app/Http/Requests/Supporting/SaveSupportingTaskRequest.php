<?php

namespace App\Http\Requests\Supporting;

use App\Enums\SupportingTaskCategory;
use App\Enums\SupportingTaskStatus;
use App\Enums\TaskPriority;
use App\Models\SupportingTask;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSupportingTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('supportingTask');

        if ($task instanceof SupportingTask) {
            return $this->user()->can('update', $task);
        }

        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace
            && $this->user()->can('create', [SupportingTask::class, $workspace]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['required', Rule::enum(SupportingTaskCategory::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'status' => ['required', Rule::enum(SupportingTaskStatus::class)],
            'assignee_public_id' => ['nullable', 'string', 'size:26'],
            'due_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $publicId = $this->string('assignee_public_id')->toString();
            $workspace = $this->workspace();

            if ($publicId !== '' && $workspace && ! $workspace->memberships()->active()
                ->whereHas('user', fn ($user) => $user->where('public_id', $publicId))
                ->exists()) {
                $validator->errors()->add('assignee_public_id', 'The assignee must be an active member of this workspace.');
            }
        });
    }

    private function workspace(): ?Workspace
    {
        $workspace = $this->route('workspace');

        if ($workspace instanceof Workspace) {
            return $workspace;
        }

        $task = $this->route('supportingTask');

        return $task instanceof SupportingTask ? $task->workspace : null;
    }
}
