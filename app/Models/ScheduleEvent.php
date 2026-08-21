<?php

namespace App\Models;

use App\Enums\WorkspaceMemberStatus;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class ScheduleEvent extends Model
{
    use GeneratesPublicId, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id', 'project_id', 'creator_id', 'title', 'description', 'start_at', 'end_at',
        'timezone', 'color', 'meeting_url', 'version',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'schedule_event_attendees')->withPivot('response');
    }

    public function meetingMinute(): HasOne
    {
        return $this->hasOne(MeetingMinute::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->whereHas('workspace.memberships', fn (Builder $membership) => $membership
                ->where('user_id', $user->id)
                ->where('status', WorkspaceMemberStatus::ACTIVE->value))
            ->where(fn (Builder $access) => $access
                ->whereNull('project_id')
                ->orWhereHas('project', fn (Builder $project) => $project->visibleTo($user)));
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        return Auth::check() ? $query->visibleTo(Auth::user()) : $query;
    }
}
