<?php

namespace App\Http\Requests\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('featureRequest') ?? $this->route('projectRequest');

        return $subject && $this->user()->can('departmentDecide', $subject);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject', 'needs_info'])],
            'note' => [
                Rule::requiredIf(fn () => $this->input('decision') !== 'approve'),
                'nullable', 'string', 'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return ['note.required' => 'Tuliskan alasan agar requester tahu apa yang perlu dilakukan.'];
    }
}
