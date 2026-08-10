<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'actor_id',
        'subject_type',
        'subject_id',
        'action',
        'metadata_json',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(Workspace $workspace, Model $subject, string $action, ?User $actor = null, array $metadata = [], ?string $ipAddress = null): self
    {
        return self::create([
            'workspace_id' => $workspace->id,
            'actor_id' => $actor?->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'metadata_json' => $metadata ?: null,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
