<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class ToggleReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('react', $this->route('message'));
    }

    public function rules(): array
    {
        return ['emoji' => ['required', 'string', 'max:32']];
    }
}
