<?php

namespace App\Http\Requests\Workspace;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');

        if (! $this->user()->can('update', $member)) {
            return false;
        }

        $actorRole = $member->workspace->memberships()->where('user_id', $this->user()->id)->value('role');

        return $actorRole === WorkspaceRole::OWNER->value || $this->input('role') !== WorkspaceRole::ADMIN->value;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in([
                WorkspaceRole::ADMIN->value,
                WorkspaceRole::MANAGER->value,
                WorkspaceRole::SUPERVISOR->value,
                WorkspaceRole::MEMBER->value,
                WorkspaceRole::VIEWER->value,
                WorkspaceRole::REQUESTER->value,
            ])],
            'status' => ['required', Rule::enum(WorkspaceMemberStatus::class)],
        ];
    }
}
