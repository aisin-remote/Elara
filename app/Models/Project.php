<?php

namespace App\Models;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    use GeneratesPublicId, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'owner_id',
        'name',
        'type',
        'description',
        'color',
        'status',
        'start_date',
        'due_date',
        'version',
        'archived_at',
    ];

    protected $casts = [
        'type' => ProjectType::class,
        'status' => ProjectStatus::class,
        'start_date' => 'date',
        'due_date' => 'date',
        'archived_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function activityLogs(): HasMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function taskStatuses(): HasMany
    {
        return $this->hasMany(TaskStatus::class)->orderBy('position');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('target_date');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function scheduleEvents(): HasMany
    {
        return $this->hasMany(ScheduleEvent::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function taskProgress(): array
    {
        $rows = $this->tasks()
            ->join('task_statuses', 'task_statuses.id', '=', 'tasks.status_id')
            ->where('task_statuses.category', '!=', TaskStatusCategory::CANCELLED->value)
            ->groupBy('task_statuses.category')
            ->selectRaw('task_statuses.category as category, count(*) as total, count(tasks.completed_at) as completed, sum(case when tasks.completed_at is null and tasks.due_at < ? then 1 else 0 end) as overdue', [now()])
            ->get();

        $total = (int) $rows->sum('total');
        $completed = (int) $rows->sum('completed');
        $inCategories = fn (array $categories) => (int) $rows->whereIn('category', $categories)->sum('total');

        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total ? (int) round($completed / $total * 100) : 0,
            'overdue' => (int) $rows->sum('overdue'),
            // Keys match the task list `tab` filter so every count can link somewhere.
            'buckets' => [
                'todo' => $inCategories([TaskStatusCategory::BACKLOG->value, TaskStatusCategory::TODO->value]),
                'in_progress' => $inCategories([TaskStatusCategory::IN_PROGRESS->value]),
                'completed' => $inCategories([TaskStatusCategory::COMPLETED->value]),
            ],
        ];
    }

    public function scheduleHealth(int $percentage): ?array
    {
        if (! $this->start_date || ! $this->due_date) {
            return null;
        }

        $span = $this->due_date->getTimestamp() - $this->start_date->getTimestamp();
        $elapsed = $span > 0
            ? (int) round(min(1, max(0, (now()->getTimestamp() - $this->start_date->getTimestamp()) / $span)) * 100)
            : 100;
        $daysLeft = (int) ceil(now()->diffInSeconds($this->due_date, false) / 86400);

        return [
            'elapsed' => $elapsed,
            'days_left' => $daysLeft,
            'state' => match (true) {
                $percentage >= 100 => 'complete',
                $daysLeft < 0 => 'overdue',
                // ponytail: flat 10-point gap, tune here if it cries wolf on long projects
                $elapsed - $percentage > 10 => 'behind',
                default => 'on_track',
            },
        ];
    }

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    /** Delivery work. Every surface labelled "Projects" uses this. */
    public function scopeDelivery(Builder $query): Builder
    {
        return $query->where('type', ProjectType::PROJECT->value);
    }

    /** Standing systems. The Feature menu and the feature-request form use this. */
    public function scopeSystems(Builder $query): Builder
    {
        return $query->where('type', ProjectType::SYSTEM->value);
    }

    public function isSystem(): bool
    {
        return $this->type === ProjectType::SYSTEM;
    }

    /** The person accountable for a system: its first project manager, by id. */
    public function pic(): ?User
    {
        return $this->members()
            ->wherePivot('role', ProjectMemberRole::MANAGER->value)
            ->orderBy('project_members.id')
            ->first();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $access) use ($user) {
            $access->whereHas('workspace.memberships', fn (Builder $membership) => $membership
                ->where('user_id', $user->id)
                ->where('status', WorkspaceMemberStatus::ACTIVE->value)
                ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value]))
                ->orWhereHas('memberships', fn (Builder $membership) => $membership->where('user_id', $user->id));
        });
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (Auth::check()) {
            $query->whereHas('workspace.memberships', fn (Builder $membership) => $membership
                ->where('user_id', Auth::id())
                ->where('status', WorkspaceMemberStatus::ACTIVE->value));
        }

        return $query;
    }
}
