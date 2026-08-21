<?php

namespace App\Http\Requests\MeetingMinute;

use App\Enums\MeetingMinutePublicationStatus;
use App\Models\MeetingMinute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetingMinutePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('meetingMinute') instanceof MeetingMinute
            && $this->user()->can('managePublication', $this->route('meetingMinute'));
    }

    public function rules(): array
    {
        return ['publication_status' => ['required', Rule::enum(MeetingMinutePublicationStatus::class)]];
    }
}
