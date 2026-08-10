<?php

namespace App\Http\Requests\Request;

use App\Enums\FeatureRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideFeatureRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('decide', $this->route('featureRequest'));
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([
                FeatureRequestStatus::APPROVED->value,
                FeatureRequestStatus::REJECTED->value,
                FeatureRequestStatus::NEEDS_INFO->value,
            ])],
            // Refusing and asking for more both have to say something. The Action enforces
            // this too; here it is so the requester gets a field error, not a 422 blob.
            'decision_note' => [
                Rule::requiredIf(fn () => in_array($this->input('decision'), [
                    FeatureRequestStatus::REJECTED->value,
                    FeatureRequestStatus::NEEDS_INFO->value,
                ], true)),
                'nullable', 'string', 'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'decision_note.required' => 'Say why, so the requester knows what to do next.',
        ];
    }
}
