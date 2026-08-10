<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('comment'));
    }

    public function rules(): array
    {
        return [];
    }
}
