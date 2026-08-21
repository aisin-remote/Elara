<?php

namespace App\Models;

use App\Enums\FeatureRequestStatus;
use App\Enums\RequestUrgency;
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

class FeatureRequest extends Model
{
    use GeneratesPublicId, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id', 'project_id', 'requester_id', 'title', 'problem', 'desired_outcome', 'benefit',
        'urgency', 'status', 'reviewed_by', 'reviewed_at', 'decision_note', 'feature_id',
        'estimated_minutes', 'version', 'organization_user_id', 'requester_job_rank_code',
        'requester_job_rank_name', 'requester_division_external_id', 'requester_division_code',
        'requester_division_name', 'requester_department_external_id', 'requester_department_code',
        'requester_department_name', 'requester_section_external_id', 'requester_section_name',
        'department_reviewed_by', 'department_reviewed_at', 'department_decision_note', 'needs_info_stage',
    ];

    // assignee_id and the scheduled dates are deliberately not fillable: the planner owns them.
    protected $casts = [
        'urgency' => RequestUrgency::class,
        'status' => FeatureRequestStatus::class,
        'reviewed_at' => 'datetime',
        'department_reviewed_at' => 'datetime',
        'scheduled_start' => 'datetime',
        'scheduled_due' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The system this request changes. */
    public function system(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function departmentReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'department_reviewed_by');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
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

    public function discussionComments(): MorphMany
    {
        return $this->morphMany(DiscussionComment::class, 'subject');
    }

    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where('status', FeatureRequestStatus::PENDING_REVIEW->value);
    }

    public function scopeAwaitingDepartment(Builder $query): Builder
    {
        return $query->where('status', FeatureRequestStatus::PENDING_DEPARTMENT->value);
    }

    /**
     * A requester sees their own submissions; everyone on the delivery desk sees all of
     * them, because approving work you cannot read is not a review. The workspace is
     * explicit: a scope cannot read it off $this, which is an empty model here.
     */
    public function scopeVisibleTo(Builder $query, User $user, Workspace $workspace, ?int $departmentId = null): Builder
    {
        $role = $workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->first()?->role;

        if ($role?->canAccessDeliveryDesk()) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user, $departmentId): void {
            $visible->where('requester_id', $user->id);

            if ($departmentId) {
                $visible->orWhere('requester_department_external_id', $departmentId);
            }
        });
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
