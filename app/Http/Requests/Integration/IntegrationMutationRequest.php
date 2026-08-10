<?php

namespace App\Http\Requests\Integration;

use App\Enums\IntegrationProvider;
use App\Models\IntegrationConnection;
use Illuminate\Foundation\Http\FormRequest;

class IntegrationMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $connection = $this->route('connection');

        return $connection instanceof IntegrationConnection && $this->user()->can('update', $connection);
    }

    public function rules(): array
    {
        $provider = $this->route('connection')->provider;

        return match ($provider) {
            IntegrationProvider::SLACK => ['channel' => ['required', 'string', 'max:80'], 'message' => ['required', 'string', 'max:3000']],
            IntegrationProvider::GOOGLE_DRIVE => ['file_id' => ['required', 'string', 'max:255'], 'project_public_id' => ['required', 'string', 'size:26']],
            IntegrationProvider::GITHUB => ['repository' => ['required', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', 'max:200'], 'url' => ['required', 'url:https', 'max:2048'], 'task_public_id' => ['required', 'string', 'size:26']],
            IntegrationProvider::ZOOM => ['schedule_event_public_id' => ['required', 'string', 'size:26'], 'topic' => ['required', 'string', 'max:200']],
        };
    }
}
