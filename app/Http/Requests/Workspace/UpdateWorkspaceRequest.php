<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageSettings', $this->route('workspace'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:16'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', 'in:en,id'],
            'week_start' => ['required', 'integer', 'between:0,6'],
        ];
    }
}
