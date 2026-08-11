<?php

namespace App\Http\Requests\Supporting;

use Illuminate\Foundation\Http\FormRequest;

class SupportingTaskMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('supportingTask'));
    }

    public function rules(): array
    {
        return [];
    }
}
