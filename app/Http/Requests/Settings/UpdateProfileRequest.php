<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->email))]);
    }

    public function rules(): array
    {
        $emailChanged = $this->input('email') !== $this->user()->email;

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->user()),
                function (string $attribute, mixed $value, \Closure $fail) use ($emailChanged): void {
                    if ($emailChanged && $this->user()->isOrganizationManaged()) {
                        $fail('Your email is managed by the company directory and cannot be changed in Orbitra.');
                    }
                },
            ],
            'current_password' => [Rule::requiredIf($emailChanged), 'nullable', 'current_password'],
            'avatar' => ['nullable', File::image()->max(2048)],
            'remove_avatar' => ['nullable', 'boolean'],
            'phone' => ['nullable', 'string', 'max:32'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'locale' => ['required', Rule::in(['en', 'id'])],
            'timezone' => ['required', 'timezone'],
            'theme' => ['required', Rule::in(['light', 'dark'])],
        ];
    }
}
