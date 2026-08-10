<?php

namespace App\Http\Requests\Task;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class ReorderTaskStatusesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageWorkflow', [Task::class, $this->route('project')]);
    }

    public function rules(): array
    {
        return [
            'status_public_ids' => ['required', 'array', 'min:1', 'max:30'],
            'status_public_ids.*' => ['string', 'size:26', 'distinct'],
        ];
    }
}
