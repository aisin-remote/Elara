<?php

namespace App\Models;

use App\Enums\MeetingMinutePublicationStatus;
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
        'publication_status', 'published_at', 'published_by', 'locked_at', 'locked_by',
    ];

    protected $casts = [
        'meeting_at' => 'datetime',
        'publication_status' => MeetingMinutePublicationStatus::class,
        'published_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

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

    public function revisions(): HasMany
    {
        return $this->hasMany(MeetingMinuteRevision::class)->latest('revision');
    }

    public function discussionComments(): MorphMany
    {
        return $this->morphMany(DiscussionComment::class, 'subject');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $visible) use ($user): void {
            $visible->where('creator_id', $user->id)
                ->orWhereHas('scheduleEvent.attendees', fn (Builder $attendees) => $attendees->where('users.id', $user->id))
                ->orWhereHas('items', fn (Builder $items) => $items->where('pic_user_id', $user->id))
                ->orWhereHas('workspace.memberships', fn (Builder $membership) => $membership
                    ->where('user_id', $user->id)
                    ->where('status', WorkspaceMemberStatus::ACTIVE->value));
        });
    }

    public function isLocked(): bool
    {
        return $this->publication_status === MeetingMinutePublicationStatus::LOCKED;
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        return Auth::check() ? $query->visibleTo(Auth::user()) : $query;
    }
}
