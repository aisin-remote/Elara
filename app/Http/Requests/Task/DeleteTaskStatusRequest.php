<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('status'));
    }

    public function rules(): array
    {
        return ['replacement_status_public_id' => ['required', 'string', 'size:26']];
    }
}
