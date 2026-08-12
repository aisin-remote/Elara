<?php

namespace App\Http\Requests\Task;

use App\Models\Task;
use App\Models\TaskProperty;
use Illuminate\Foundation\Http\FormRequest;

class TaskPropertyMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof TaskProperty
            && $this->user()->can('manageWorkflow', [Task::class, $property->project]);
    }

    public function rules(): array
    {
        return [];
    }
}
