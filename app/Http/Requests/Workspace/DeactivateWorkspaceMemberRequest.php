<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class DeactivateWorkspaceMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('member'));
    }

    public function rules(): array
    {
        return [];
    }
}
