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
            // Both dates or neither: one date on its own is not a slot, and the planner would
            // overwrite it on its next run anyway.
            'scheduled_start' => ['required_with:scheduled_due', 'nullable', 'date'],
            'scheduled_due' => ['required_with:scheduled_start', 'nullable', 'date', 'after_or_equal:scheduled_start'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision_note.required' => 'Say why, so the requester knows what to do next.',
            'scheduled_start.required_with' => 'Give a start date too, or leave both empty for automatic scheduling.',
            'scheduled_due.required_with' => 'Give a due date too, or leave both empty for automatic scheduling.',
            'scheduled_due.after_or_equal' => 'The due date cannot be before the start date.',
        ];
    }
}
