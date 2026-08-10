<?php

namespace App\Http\Requests\Schedule;

class UpdateScheduleEventRequest extends ScheduleEventMutationRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    public function rules(): array
    {
        return [
            ...$this->eventRules($this->route('event')->workspace),
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
