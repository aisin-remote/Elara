<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class ProjectMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->routeIs('internal.projects.restore') ? 'restore' : 'delete';

        return $this->user()->can($ability, $this->route('project'));
    }

    public function rules(): array
    {
        return [];
    }
}
