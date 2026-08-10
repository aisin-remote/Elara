<?php

namespace App\Http\Requests;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace && $this->user()?->can('view', $workspace);
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'min:2', 'max:100']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['q' => trim((string) $this->input('q')) ?: null]);
    }
}
