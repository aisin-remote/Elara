<?php

namespace App\Http\Requests\MeetingMinute;

use App\Enums\MeetingMinuteStatus;
use App\Models\MeetingMinuteItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetingMinuteItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('meetingMinuteItem') instanceof MeetingMinuteItem
            && $this->user()->can('update', $this->route('meetingMinuteItem'));
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(MeetingMinuteStatus::class)]];
    }
}
