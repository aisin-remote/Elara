<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->two_factor_secret;
    }

    public function rules(): array
    {
        return ['code' => ['required', 'digits:6']];
    }
}
