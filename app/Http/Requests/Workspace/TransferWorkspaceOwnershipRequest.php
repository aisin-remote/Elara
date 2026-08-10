<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class TransferWorkspaceOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transferOwnership', $this->route('workspace'));
    }

    public function rules(): array
    {
        return ['member_public_id' => ['required', 'string', 'size:26']];
    }
}
