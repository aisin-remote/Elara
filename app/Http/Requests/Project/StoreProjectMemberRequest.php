<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectMemberRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'member_public_id' => ['required', 'string', 'size:26'],
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
        ];
    }
}
