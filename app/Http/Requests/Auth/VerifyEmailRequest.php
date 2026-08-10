<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hash_equals((string) $this->user()->public_id, (string) $this->route('id'))
            && hash_equals(sha1($this->user()->getEmailForVerification()), (string) $this->route('hash'));
    }

    public function rules(): array
    {
        return [];
    }
}
