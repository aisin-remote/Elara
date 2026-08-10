<?php

namespace App\Http\Requests;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace && $this->user()?->can('view', $workspace);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(WorkspaceRole::class)],
            'presence' => ['nullable', Rule::in(['active', 'offline'])],
            'project' => ['nullable', 'ulid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['search' => trim((string) $this->input('search')) ?: null]);
    }
}
