<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('item')->task);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'is_completed' => ['required', 'boolean'],
        ];
    }
}
