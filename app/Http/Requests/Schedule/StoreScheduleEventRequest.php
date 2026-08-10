<?php

namespace App\Http\Requests\Schedule;

use App\Models\ScheduleEvent;

class StoreScheduleEventRequest extends ScheduleEventMutationRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [ScheduleEvent::class, $this->route('workspace')]);
    }

    public function rules(): array
    {
        return $this->eventRules($this->route('workspace'));
    }
}
