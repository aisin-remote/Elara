<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workspace_public_id' => ['required', 'string', 'size:26'],
            'plan' => ['required', Rule::in(array_keys(config('plans.plans')))],
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
        ];
    }
}
