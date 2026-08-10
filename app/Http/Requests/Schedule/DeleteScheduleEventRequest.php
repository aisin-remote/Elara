<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class DeleteScheduleEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('event'));
    }

    public function rules(): array
    {
        return [];
    }
}
