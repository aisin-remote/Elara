<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class IntegrationCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required_without:error', 'nullable', 'string', 'max:2048'],
            'state' => ['required', 'string', 'max:255'],
            'error' => ['nullable', 'string', 'max:255'],
        ];
    }
}
