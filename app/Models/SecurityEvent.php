<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'event', 'ip_address', 'user_agent', 'metadata_json', 'created_at'];

    protected $casts = ['metadata_json' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(?User $user, string $event, ?string $ipAddress, ?string $userAgent, array $metadata = []): self
    {
        return self::create([
            'user_id' => $user?->id,
            'event' => $event,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata_json' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
