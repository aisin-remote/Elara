<?php

namespace App\Http\Requests\Feature;

use App\Enums\ProjectType;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Project::class, $this->route('workspace')]);
    }

    public function rules(): array
    {
        $workspace = $this->route('workspace');

        return [
            'system_public_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('projects', 'public_id')->where(fn ($query) => $query
                    ->where('workspace_id', $workspace->id)
                    ->where('type', ProjectType::SYSTEM->value)
                    ->whereNull('archived_at')
                    ->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:200'],
            'description' => $this->boolean('generate_with_ai')
                ? ['required', 'string', 'min:80', 'max:5000']
                : ['required', 'string', 'min:20', 'max:5000'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'generate_with_ai' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.min' => $this->boolean('generate_with_ai')
                ? 'Give AI at least 80 characters covering the problem, expected behavior, users, and acceptance criteria.'
                : 'Describe the feature in at least 20 characters.',
        ];
    }
}
