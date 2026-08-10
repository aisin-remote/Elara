<?php

namespace App\Models;

use App\Enums\CheckpointStatus;
use App\Enums\WorkspaceMemberStatus;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * A pause in delivery while the requester confirms something only they can judge. It carries
 * its own deadline so that changing the workspace window never moves a countdown already
 * running (PRD-07).
 */
class ValidationCheckpoint extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = [
        'workspace_id', 'task_id', 'subject_type', 'subject_id', 'requester_id',
        'reason', 'status', 'opened_at', 'expires_at', 'responded_at', 'response_note',
        'reminded_at', 'final_warning_at',
    ];

    protected $casts = [
        'status' => CheckpointStatus::class,
        'opened_at' => 'datetime',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'reminded_at' => 'datetime',
        'final_warning_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', CheckpointStatus::OPEN->value);
    }

    /** Whole days, floored, and never negative — "0 days left" reads better than "-1". */
    public function daysLeft(): int
    {
        return max(0, (int) floor(now()->diffInDays($this->expires_at, false)));
    }

    public function countdown(): string
    {
        if (! $this->status->isCountingDown()) {
            return $this->status->label();
        }

        return match ($days = $this->daysLeft()) {
            0 => 'Due today',
            1 => '1 day left',
            default => $days.' days left',
        };
    }

    /** The requester owns this one; nobody else needs it in a list. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('requester_id', $user->id);
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
