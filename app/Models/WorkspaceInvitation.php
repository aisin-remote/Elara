<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceInvitation extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = [
        'workspace_id',
        'email',
        'role',
        'invited_by',
        'token_hash',
        'expires_at',
        'accepted_at',
        'rejected_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'role' => WorkspaceRole::class,
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->rejected_at === null && $this->expires_at->isFuture();
    }
}
