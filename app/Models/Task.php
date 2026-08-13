<?php

namespace App\Models;

use App\Enums\DependencyType;
use App\Enums\ProjectType;
use App\Enums\TaskPriority;
use App\Enums\WorkspaceMemberStatus;
use App\Services\OrganizationDirectory;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Task extends Model
{
    use GeneratesPublicId, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id', 'project_id', 'feature_id', 'status_id', 'category_id', 'milestone_id', 'creator_id', 'title', 'description',
        'priority', 'start_at', 'due_at', 'baseline_start_at', 'baseline_due_at', 'completed_at', 'status_changed_at', 'estimate_minutes', 'position', 'version', 'archived_at',
        'requires_user_validation', 'validation_reason',
    ];

    protected $casts = [
        'priority' => TaskPriority::class,
        'start_at' => 'datetime',
        'due_at' => 'datetime',
        'baseline_start_at' => 'datetime',
        'baseline_due_at' => 'datetime',
        'completed_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'archived_at' => 'datetime',
        'requires_user_validation' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Set when the task came from an approved feature request; null for loose maintenance work. */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'status_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'category_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot(['assigned_by', 'assigned_at']);
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers');
    }

    /** Prerequisites linked through task_dependencies (any type). */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'task_id', 'depends_on_task_id')
            ->withPivot(['type', 'lag_minutes'])
            ->withTimestamps();
    }

    /** Tasks currently waiting on this task. */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'depends_on_task_id', 'task_id')
            ->withPivot(['type', 'lag_minutes'])
            ->withTimestamps();
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TaskTimeEntry::class)->latest('worked_on');
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->whereNull('completed_at')->where(function (Builder $outer): void {
            $outer->whereHas('dependencies', function (Builder $dependency): void {
                $dependency->where('task_dependencies.type', DependencyType::FINISH_TO_START->value)
                    ->whereNull('tasks.completed_at');
            })->orWhereHas('dependencies', function (Builder $dependency): void {
                $dependency->where('task_dependencies.type', DependencyType::START_TO_START->value)
                    ->whereNull('tasks.completed_at')
                    ->where(function (Builder $started): void {
                        $started->whereNull('tasks.start_at')->orWhere('tasks.start_at', '>', now());
                    });
            });
        });
    }

    public function isBlocked(): bool
    {
        if ($this->completed_at !== null) {
            return false;
        }

        $dependencies = $this->relationLoaded('dependencies')
            ? $this->dependencies
            : $this->dependencies()->get();

        return $dependencies->contains(function (Task $dependency): bool {
            $type = DependencyType::tryFrom((string) ($dependency->pivot->type ?? DependencyType::FINISH_TO_START->value))
                ?? DependencyType::FINISH_TO_START;

            if (! $type->blocksStart()) {
                return false;
            }

            if ($type === DependencyType::FINISH_TO_START) {
                return $dependency->completed_at === null;
            }

            // Start-to-start: blocked until the prerequisite has begun (or finished).
            return $dependency->completed_at === null
                && ($dependency->start_at === null || $dependency->start_at->isFuture());
        });
    }

    public function loggedMinutes(): int
    {
        return (int) ($this->relationLoaded('timeEntries')
            ? $this->timeEntries->sum('minutes')
            : $this->timeEntries()->sum('minutes'));
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position');
    }

    public function propertyValues(): HasMany
    {
        return $this->hasMany(TaskPropertyValue::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where(function (Builder $visible) use ($user): void {
            $visible->whereHas('project', fn (Builder $project) => $project->visibleTo($user))
                ->orWhereHas('project', fn (Builder $project) => $project
                    ->where('type', ProjectType::PERSONAL->value)
                    ->where('owner_id', $user->id)
                    ->whereHas('workspace.memberships', fn (Builder $membership) => $membership
                        ->where('user_id', $user->id)
                        ->where('status', WorkspaceMemberStatus::ACTIVE->value)));
        });

        if (! config('organization.required')) {
            return $query;
        }

        $visibility = app(OrganizationDirectory::class)->taskVisibility($user);

        return $query->where(function (Builder $tasks) use ($user, $visibility): void {
            $tasks->whereHas('project', fn (Builder $project) => $project
                ->where('type', ProjectType::PERSONAL->value)
                ->where('owner_id', $user->id));

            foreach ($visibility as $workspaceId => $userIds) {
                $tasks->orWhere(function (Builder $workspaceTasks) use ($workspaceId, $userIds): void {
                    $workspaceTasks->where('tasks.workspace_id', $workspaceId)
                        ->whereHas('assignees', fn (Builder $assignees) => $assignees->whereIn('users.id', $userIds));
                });
            }

        });
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (Auth::check()) {
            Auth::user()->isRequester()
                ? $query
                    ->whereHas('workspace.memberships', fn (Builder $membership) => $membership
                        ->where('user_id', Auth::id())
                        ->where('status', WorkspaceMemberStatus::ACTIVE->value))
                    ->whereHas('project', fn (Builder $project) => $project
                        ->where('type', '!=', ProjectType::PERSONAL->value)
                        ->orWhere('owner_id', Auth::id()))
                : $query->visibleTo(Auth::user());
        }

        return $query;
    }
}
