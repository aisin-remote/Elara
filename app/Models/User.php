<?php

namespace App\Models;

use App\Services\OrganizationDirectory;
use App\Support\GeneratesPublicId;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable implements MustVerifyEmail
{
    use Billable, GeneratesPublicId, HasFactory, HasPushSubscriptions, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'auth_source',
        'organization_user_id',
        'organization_synced_at',
        'password',
        'email_verified_at',
        'avatar_path',
        'phone',
        'job_title',
        'company',
        'bio',
        'locale',
        'timezone',
        'theme',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'organization_user_id' => 'integer',
        'organization_synced_at' => 'datetime',
        'password' => 'hashed',
        'last_seen_at' => 'datetime',
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
        'two_factor_confirmed_at' => 'datetime',
    ];

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function isOrganizationManaged(): bool
    {
        return $this->auth_source === 'organization' && $this->organization_user_id !== null;
    }

    public function getAuthPassword(): string
    {
        if ($this->isOrganizationManaged()) {
            return app(OrganizationDirectory::class)->credentialHash($this) ?? $this->password;
        }

        return $this->password;
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /** True when every active membership this user holds is a requester membership. */
    public function isRequester(): bool
    {
        $memberships = $this->workspaceMemberships()->active()->get();

        return $memberships->isNotEmpty()
            && $memberships->every(fn (WorkspaceMember $membership) => ! $membership->role->canAccessDeliveryDesk());
    }

    /** Where this user belongs after signing in. */
    public function homePath(): string
    {
        return $this->isRequester() ? '/desk' : '/app';
    }

    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot(['public_id', 'role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'creator_id');
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees');
    }

    public function taskComments(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'author_id');
    }

    public function createdScheduleEvents(): HasMany
    {
        return $this->hasMany(ScheduleEvent::class, 'creator_id');
    }

    public function scheduleEvents(): BelongsToMany
    {
        return $this->belongsToMany(ScheduleEvent::class, 'schedule_event_attendees')->withPivot('response');
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['last_read_message_id', 'joined_at', 'muted_until'])
            ->withTimestamps();
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'requester_id');
    }

    public function createdSupportingTasks(): HasMany
    {
        return $this->hasMany(SupportingTask::class, 'creator_id');
    }

    public function assignedSupportingTasks(): HasMany
    {
        return $this->hasMany(SupportingTask::class, 'assignee_id');
    }

    public function receivesBroadcastNotificationsOn(): string
    {
        return 'users.'.$this->public_id;
    }
}
