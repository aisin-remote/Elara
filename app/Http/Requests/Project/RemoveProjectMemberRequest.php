<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class RemoveProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('project'));
    }

    public function rules(): array
    {
        return [];
    }
}
