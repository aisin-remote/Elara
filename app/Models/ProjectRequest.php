<?php

namespace App\Models;

use App\Enums\ProjectRequestStatus;
use App\Enums\WorkspaceMemberStatus;
use App\Services\OrganizationDirectory;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class ProjectRequest extends Model
{
    use GeneratesPublicId, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id', 'requester_id', 'title', 'benefit', 'concept', 'business_process', 'flow',
        'target_date', 'status', 'schedule_event_id', 'meeting_held_at', 'meeting_note',
        'spv_id', 'spv_at', 'spv_note', 'manager_id', 'manager_at', 'manager_note',
        'project_id', 'estimated_minutes', 'version',
        'organization_user_id', 'requester_job_rank_code', 'requester_job_rank_name',
        'requester_division_external_id', 'requester_division_code', 'requester_division_name',
        'requester_department_external_id', 'requester_department_code', 'requester_department_name',
        'requester_section_external_id', 'requester_section_name', 'department_reviewed_by',
        'department_reviewed_at', 'department_decision_note', 'needs_info_stage',
    ];

    // assignee_id and the scheduled dates are deliberately not fillable: the planner owns them.
    protected $casts = [
        'status' => ProjectRequestStatus::class,
        'target_date' => 'date',
        'meeting_held_at' => 'datetime',
        'spv_at' => 'datetime',
        'manager_at' => 'datetime',
        'department_reviewed_at' => 'datetime',
        'scheduled_start' => 'datetime',
        'scheduled_due' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'spv_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function departmentReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'department_reviewed_by');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ScheduleEvent::class, 'schedule_event_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** Screenshots, spreadsheets, mockups — kept with the request, not filed into a board. */
    public function attachments(): MorphMany
    {
        return $this->morphMany(ProjectFile::class, 'attachable')->latest('id');
    }

    public function breakdowns(): MorphMany
    {
        return $this->morphMany(TaskBreakdown::class, 'subject');
    }

    public function scopeAwaitingDecision(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ProjectRequestStatus::PENDING_MEETING->value,
            ProjectRequestStatus::PENDING_SPV->value,
            ProjectRequestStatus::PENDING_MANAGER->value,
        ]);
    }

    public function scopeAwaitingDepartment(Builder $query): Builder
    {
        return $query->where('status', ProjectRequestStatus::PENDING_DEPARTMENT->value);
    }

    public function scopeVisibleTo(Builder $query, User $user, Workspace $workspace): Builder
    {
        $role = $workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->first()?->role;

        return $role?->canAccessDeliveryDesk()
            ? $query
            : $query->where('requester_id', $user->id);
    }

    /** The meeting has to have happened before anyone may sign. */
    public function meetingHeld(): bool
    {
        return $this->meeting_held_at !== null;
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (Auth::check()) {
            $user = Auth::user();
            $departmentId = app(OrganizationDirectory::class)->profile($user)['department_id'] ?? null;

            $query->where(function (Builder $visible) use ($user, $departmentId): void {
                $visible->where('requester_id', $user->id)
                    ->orWhereHas('workspace.memberships', fn (Builder $membership) => $membership
                        ->where('user_id', $user->id)
                        ->where('status', WorkspaceMemberStatus::ACTIVE->value));

                if ($departmentId) {
                    $visible->orWhere('requester_department_external_id', $departmentId);
                }
            });
        }

        return $query;
    }
}
