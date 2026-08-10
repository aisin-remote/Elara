<?php

namespace App\Http\Requests\Messaging;

use App\Enums\ConversationType;
use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Conversation::class, $this->route('workspace')]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ConversationType::class)],
            'title' => ['nullable', 'string', 'max:160'],
            'project_public_id' => ['nullable', 'string', 'size:26'],
            'participant_public_ids' => ['nullable', 'array', 'max:100'],
            'participant_public_ids.*' => ['string', 'size:26', 'distinct'],
        ];
    }
}
