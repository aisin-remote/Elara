<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Project::class, $this->route('workspace')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', Rule::in([
                ProjectStatus::PLANNED->value,
                ProjectStatus::ACTIVE->value,
                ProjectStatus::ON_HOLD->value,
                ProjectStatus::COMPLETED->value,
            ])],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'member_public_ids' => ['nullable', 'array'],
            'member_public_ids.*' => ['string', 'size:26', 'distinct'],
        ];
    }
}
