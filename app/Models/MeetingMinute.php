<?php

namespace App\Models;

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

class MeetingMinute extends Model
{
    use GeneratesPublicId, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id', 'creator_id', 'schedule_event_id', 'project_id', 'title', 'meeting_at', 'summary',
    ];

    protected $casts = ['meeting_at' => 'datetime'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function scheduleEvent(): BelongsTo
    {
        return $this->belongsTo(ScheduleEvent::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(MeetingMinuteItem::class)->orderBy('position');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(ProjectFile::class, 'attachable');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('workspace.memberships', fn (Builder $membership) => $membership
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value));
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        return Auth::check() ? $query->visibleTo(Auth::user()) : $query;
    }
}
