<?php

namespace App\Http\Requests\Workspace;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteWorkspaceMemberRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->email))]);
    }

    public function authorize(): bool
    {
        return $this->user()->can('invite', $this->route('workspace'));
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            // Every role except owner, which only ownership transfer may grant.
            'role' => ['required', Rule::in([
                WorkspaceRole::ADMIN->value,
                WorkspaceRole::MANAGER->value,
                WorkspaceRole::SUPERVISOR->value,
                WorkspaceRole::MEMBER->value,
                WorkspaceRole::VIEWER->value,
                WorkspaceRole::REQUESTER->value,
            ])],
        ];
    }
}
