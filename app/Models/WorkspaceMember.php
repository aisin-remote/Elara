<?php

namespace App\Models;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class WorkspaceMember extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
        'status',
        'permissions_json',
        'invited_by',
        'joined_at',
    ];

    protected $casts = [
        'role' => WorkspaceRole::class,
        'status' => WorkspaceMemberStatus::class,
        'permissions_json' => 'array',
        'joined_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', WorkspaceMemberStatus::ACTIVE->value);
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
