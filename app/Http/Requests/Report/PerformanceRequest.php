<?php

namespace App\Http\Requests\Report;

use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerformanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewReport', $this->route('workspace'));
    }

    public function rules(): array
    {
        $workspace = $this->route('workspace');

        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->filled('from') && $this->date('from')->diffInDays($this->date('to')) > 366) {
                    $fail('The reporting range may not exceed 366 days.');
                }
            }],
            'project_public_id' => ['nullable', 'string', 'size:26', function (string $attribute, mixed $value, Closure $fail) use ($workspace): void {
                $project = Project::query()->visibleTo($this->user())
                    ->where('workspace_id', $workspace->id)->where('public_id', $value)->first();
                $project ?: $fail('Choose an accessible project.');
            }],
            'member_public_id' => ['nullable', 'string', 'size:26', function (string $attribute, mixed $value, Closure $fail) use ($workspace): void {
                $exists = User::query()->where('public_id', $value)->whereHas('workspaceMemberships', fn (Builder $membership) => $membership
                    ->active()->where('workspace_id', $workspace->id))->exists();
                $exists ?: $fail('Choose an active workspace member.');
            }],
            'status_public_id' => ['nullable', 'string', 'size:26', function (string $attribute, mixed $value, Closure $fail) use ($workspace): void {
                $exists = TaskStatus::query()->where('public_id', $value)
                    ->whereHas('project', fn (Builder $project) => $project
                        ->visibleTo($this->user())->where('workspace_id', $workspace->id))->exists();
                $exists ?: $fail('Choose an accessible task status.');
            }],
            'distribution' => ['nullable', Rule::in(['status', 'priority'])],
            'gantt_view' => ['nullable', Rule::in(['projects', 'features'])],
            'gantt_member' => ['nullable', 'string', 'size:26'],
            'gantt_scale' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
        ];
    }
}
