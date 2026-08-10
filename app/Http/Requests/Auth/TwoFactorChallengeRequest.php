<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->session()->has('login.two_factor_user_id');
    }

    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:20']];
    }
}
