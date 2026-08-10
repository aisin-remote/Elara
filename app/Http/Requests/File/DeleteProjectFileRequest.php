<?php

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;

class DeleteProjectFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('file'));
    }

    public function rules(): array
    {
        return [];
    }
}
