<?php

namespace App\Http\Requests\Request;

use App\Models\ProjectRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [ProjectRequest::class, $this->route('workspace')]);
    }

    public function rules(): array
    {
        // All four narrative fields are required. A request that cannot articulate its own
        // business process is not ready for an approver's time, and the form should say so
        // before submission rather than the supervisor saying it a week later.
        return [
            'title' => ['required', 'string', 'max:200'],
            'benefit' => ['required', 'string', 'min:40', 'max:4000'],
            'concept' => ['required', 'string', 'min:40', 'max:4000'],
            'business_process' => ['required', 'string', 'min:40', 'max:4000'],
            'flow' => ['required', 'string', 'min:40', 'max:4000'],
            'target_date' => ['nullable', 'date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'benefit.min' => 'Say what the business gains — an approver cannot weigh "it would be nice".',
            'concept.min' => 'Describe what the thing actually is, in a few sentences.',
            'business_process.min' => 'Describe the process it supports or replaces.',
            'flow.min' => 'Walk through how it runs end to end.',
            'target_date.after' => 'A target date in the past cannot be planned for.',
        ];
    }
}
