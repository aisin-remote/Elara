<?php

namespace App\Http\Requests\Schedule;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Services\DepartmentWorkspaceService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequesterScheduleEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace
            && $workspace->memberships()->active()
                ->where('user_id', $this->user()->id)
                ->where('role', WorkspaceRole::REQUESTER->value)
                ->exists();
    }

    public function rules(): array
    {
        $deliveryWorkspace = app(DepartmentWorkspaceService::class)->deliveryWorkspace();

        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'meeting_url' => ['nullable', 'url:http,https', 'max:2048'],
            'attendee_public_ids' => ['required', 'array', 'min:1', 'max:20'],
            'attendee_public_ids.*' => [
                'required',
                'string',
                'size:26',
                'distinct',
                function (string $attribute, mixed $value, Closure $fail) use ($deliveryWorkspace): void {
                    $isItMember = $deliveryWorkspace->memberships()->active()
                        ->where('role', '!=', WorkspaceRole::REQUESTER->value)
                        ->whereHas('user', fn ($query) => $query->where('public_id', $value))
                        ->exists();

                    if (! $isItMember) {
                        $fail('Choose an active IT team member.');
                    }
                },
            ],
        ];
    }
}
