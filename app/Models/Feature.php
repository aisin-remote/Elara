<?php

namespace App\Models;

use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * A change to a standing system: the container for the tasks one approved feature request
 * produced. Its tasks also carry project_id, so boards, calendars, and policies are the
 * ones the delivery desk already uses.
 */
class Feature extends Model
{
    use GeneratesPublicId, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id', 'project_id', 'name', 'description',
        'status', 'starts_at', 'due_at', 'version', 'archived_at',
    ];

    // Y-m-d on the two DATE columns: a plain 'date' cast writes a time component that MySQL
    // trims and SQLite keeps, so a lookup by date stops finding the row it just wrote.
    protected $casts = [
        'starts_at' => 'date:Y-m-d',
        'due_at' => 'date:Y-m-d',
        'archived_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The system this feature changes. */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function breakdowns(): MorphMany
    {
        return $this->morphMany(TaskBreakdown::class, 'subject');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', fn (Builder $project) => $project->visibleTo($user));
    }

    /** Same shape as Project::taskProgress so the two can be rendered by one component. */
    public function progress(?User $viewer = null): array
    {
        $tasks = $this->tasks()->whereNull('archived_at');

        if ($viewer) {
            $tasks->visibleTo($viewer);
        }

        $total = (clone $tasks)
            ->whereHas('status', fn (Builder $status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value))
            ->count();

        $completed = (clone $tasks)->whereNotNull('completed_at')->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total ? (int) round($completed / $total * 100) : 0,
        ];
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
