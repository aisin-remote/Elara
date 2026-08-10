<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('milestone')->project);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'target_date' => ['required', 'date'],
            'completed' => ['required', 'boolean'],
        ];
    }
}
