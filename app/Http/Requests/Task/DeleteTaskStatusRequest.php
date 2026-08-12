<?php

namespace App\Http\Requests\Task;

use App\Models\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeleteTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('status'));
    }

    public function rules(): array
    {
        return ['replacement_status_public_id' => ['nullable', 'string', 'size:26']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = $this->route('status');

            if (! $status instanceof TaskStatus) {
                return;
            }

            if ($status->project->taskStatuses()->active()->count() <= 1) {
                $validator->errors()->add('status', 'A project must keep at least one group.');
            }

            if ($status->tasks()->withTrashed()->exists() && blank($this->input('replacement_status_public_id'))) {
                $validator->errors()->add('replacement_status_public_id', 'Choose where this group\'s tasks should move.');
            }
        });
    }
}
