<?php

namespace App\Models;

use App\Enums\WorkspaceMemberStatus;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Workspace extends Model
{
    use GeneratesPublicId, HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'organization_department_id',
        'organization_department_code',
        'name',
        'icon',
        'timezone',
        'locale',
        'settings_json',
    ];

    protected $casts = [
        'organization_department_id' => 'integer',
        'settings_json' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot(['public_id', 'role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function taskCategories(): HasMany
    {
        return $this->hasMany(TaskCategory::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
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

    public function integrationConnections(): HasMany
    {
        return $this->hasMany(IntegrationConnection::class);
    }

    public function integrationLinks(): HasMany
    {
        return $this->hasMany(IntegrationLink::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function supportingTasks(): HasMany
    {
        return $this->hasMany(SupportingTask::class);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (Auth::check()) {
            $query->whereHas('memberships', fn (Builder $membership) => $membership
                ->where('user_id', Auth::id())
                ->where('status', WorkspaceMemberStatus::ACTIVE->value));
        }

        return $query;
    }
}
