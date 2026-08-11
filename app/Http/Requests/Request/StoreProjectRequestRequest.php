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
        return [
            'title' => ['required', 'string', 'max:200'],
            'background' => ['required', 'string', 'min:20', 'max:5000'],
            'why_needed' => ['required', 'string', 'min:20', 'max:4000'],
            'objectives' => ['required', 'array', 'min:1', 'max:6'],
            'objectives.*.title' => ['required', 'string', 'max:200'],
            'objectives.*.description' => ['required', 'string', 'max:1000'],
            'illustration' => ['required', 'string', 'min:20', 'max:4000'],
            'before_state' => ['required', 'string', 'min:20', 'max:5000'],
            'after_state' => ['required', 'string', 'min:20', 'max:5000'],
            'benefits' => ['required', 'array', 'min:1', 'max:4'],
            'benefits.*' => ['required', 'string', 'max:1000'],
            'cost_items' => ['required', 'array', 'min:1', 'max:3'],
            'cost_items.*' => ['required', 'string', 'max:1000'],
            'roi' => ['required', 'string', 'min:20', 'max:4000'],
            'target_date' => ['nullable', 'date', 'after:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $objectives = collect($this->input('objectives', []))
            ->map(fn ($objective) => [
                'title' => trim((string) ($objective['title'] ?? '')),
                'description' => trim((string) ($objective['description'] ?? '')),
            ])
            ->filter(fn (array $objective) => $objective['title'] !== '' || $objective['description'] !== '')
            ->values()
            ->all();

        $this->merge([
            'objectives' => $objectives,
            'benefits' => $this->filledStrings('benefits'),
            'cost_items' => $this->filledStrings('cost_items'),
        ]);
    }

    public function messages(): array
    {
        return [
            'background.min' => 'Describe the activity or process that needs improvement.',
            'why_needed.min' => 'Explain the pain point this project should solve.',
            'objectives.min' => 'Add at least one complete objective.',
            'benefits.min' => 'Add at least one tangible or intangible benefit.',
            'cost_items.min' => 'Add at least one expected cost item.',
            'roi.min' => 'Explain the expected return or how success will be measured.',
            'target_date.after' => 'A target date in the past cannot be planned for.',
        ];
    }

    private function filledStrings(string $key): array
    {
        return collect($this->input($key, []))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }
}
