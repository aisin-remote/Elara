<?php

namespace App\Http\Requests\Schedule;

use App\Models\Project;
use App\Models\ScheduleEvent;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [ScheduleEvent::class, $this->route('workspace')]);
    }

    public function rules(): array
    {
        $workspace = $this->route('workspace');

        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'project_public_id' => ['nullable', 'string', 'size:26', function (string $attribute, mixed $value, Closure $fail) use ($workspace): void {
                if (! $value) {
                    return;
                }

                $project = Project::query()->where('workspace_id', $workspace->id)->where('public_id', $value)->first();

                if (! $project || ! $this->user()->can('view', $project)) {
                    $fail('Choose an accessible project.');
                }
            }],
        ];
    }
}
