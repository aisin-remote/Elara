<?php

namespace App\Http\Requests\Ai;

use App\Enums\ProjectType;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DraftDescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Project::class, $this->route('workspace')]);
    }

    public function rules(): array
    {
        $workspace = $this->route('workspace');

        return [
            'kind' => ['required', Rule::in(['project', 'feature'])],
            'name' => ['required', 'string', 'min:2', 'max:200'],
            'description' => ['required', 'string', 'min:3', 'max:5000'],
            'system_public_id' => [
                'nullable',
                'required_if:kind,feature',
                'string',
                'size:26',
                Rule::exists('projects', 'public_id')->where(fn ($query) => $query
                    ->where('workspace_id', $workspace->id)
                    ->where('type', ProjectType::SYSTEM->value)
                    ->whereNull('archived_at')
                    ->whereNull('deleted_at')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter the name before generating a description.',
            'description.required' => 'Write a short idea before asking AI to expand it.',
            'description.min' => 'Write at least 3 characters before asking AI to expand it.',
            'system_public_id.required_if' => 'Choose a system before generating a feature description.',
        ];
    }
}
