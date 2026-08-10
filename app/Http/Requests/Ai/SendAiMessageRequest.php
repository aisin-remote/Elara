<?php

namespace App\Http\Requests\Ai;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

class SendAiMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace
            && ! $this->user()?->isRequester()
            && $this->user()?->can('view', $workspace);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'message' => trim((string) $this->input('message')),
            'conversation_public_id' => $this->filled('conversation_public_id')
                ? trim((string) $this->input('conversation_public_id'))
                : null,
            'project_public_id' => $this->filled('project_public_id')
                ? trim((string) $this->input('project_public_id'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:4000'],
            'conversation_public_id' => ['nullable', 'string', 'size:26'],
            'project_public_id' => ['nullable', 'string', 'size:26'],
        ];
    }
}
