<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('message'));
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:10000', 'not_regex:/^\s*$/']];
    }
}
