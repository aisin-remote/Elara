<?php

namespace App\Models;

use App\Enums\BreakdownStatus;
use App\Enums\WorkspaceMemberStatus;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * One proposed task list for one request. Kept separate from the tasks it proposes so a
 * rejected draft stays auditable and a regeneration can be compared against its predecessor.
 */
class TaskBreakdown extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = [
        'workspace_id', 'subject_type', 'subject_id', 'provider', 'model', 'status',
        'payload_json', 'input_tokens', 'output_tokens', 'error_message',
        'generated_at', 'accepted_at', 'accepted_by',
    ];

    protected $casts = [
        'status' => BreakdownStatus::class,
        'payload_json' => 'array',
        'generated_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    /** @return array<int, array<string, mixed>> */
    public function tasks(): array
    {
        return $this->payload_json['tasks'] ?? [];
    }

    /** Route binding never reaches outside the workspaces this user actually belongs to. */
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

    public function totalMinutes(): int
    {
        return array_sum(array_map(fn (array $task) => (int) ($task['estimate_minutes'] ?? 0), $this->tasks()));
    }
}
