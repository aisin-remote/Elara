<?php

namespace App\Models;

use App\Enums\SupportingTaskCategory;
use App\Enums\SupportingTaskStatus;
use App\Enums\TaskPriority;
use App\Enums\WorkspaceMemberStatus;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class SupportingTask extends Model
{
    use GeneratesPublicId, SoftDeletes;

    protected $fillable = [
        'workspace_id', 'creator_id', 'requester_department_id', 'requester_department_code', 'requester_department_name',
        'assignee_id', 'title', 'description', 'category', 'priority', 'status', 'due_date', 'completed_at',
    ];

    protected $casts = [
        'category' => SupportingTaskCategory::class,
        'priority' => TaskPriority::class,
        'status' => SupportingTaskStatus::class,
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isBefore(today($this->workspace->timezone))
            && ! in_array($this->status, [SupportingTaskStatus::COMPLETED, SupportingTaskStatus::CANCELLED], true);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (Auth::check()) {
            $query->where(fn (Builder $access) => $access
                ->where('creator_id', Auth::id())
                ->orWhereHas('workspace.memberships', fn (Builder $membership) => $membership
                    ->where('user_id', Auth::id())
                    ->where('status', WorkspaceMemberStatus::ACTIVE->value)));
        }

        return $query;
    }
}
