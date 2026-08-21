<?php

namespace App\Http\Requests\Ai;

use App\Models\MeetingMinute;
use Illuminate\Foundation\Http\FormRequest;

class DraftMeetingSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [MeetingMinute::class, $this->route('workspace')]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.content' => ['required', 'string', 'max:5000'],
            'items.*.pic_name' => ['nullable', 'string', 'max:120'],
            'items.*.due_date' => ['nullable', 'date'],
            'items.*.status' => ['nullable', 'string', 'max:24'],
        ];
    }
}
