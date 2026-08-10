<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class TaskMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->routeIs('internal.tasks.restore') ? 'restore' : 'delete';

        return $this->user()->can($ability, $this->route('task'));
    }

    public function rules(): array
    {
        return [];
    }
}
