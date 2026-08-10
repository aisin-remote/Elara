<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class MarkConversationReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('markRead', $this->route('conversation'));
    }

    public function rules(): array
    {
        return ['message_public_id' => ['nullable', 'string', 'size:26']];
    }
}
