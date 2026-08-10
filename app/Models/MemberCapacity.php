<?php

namespace App\Models;

use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCapacity extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = ['workspace_id', 'user_id', 'hours_per_day', 'working_days', 'effective_from'];

    protected $casts = [
        'hours_per_day' => 'float',
        'working_days' => 'array',
        // Y-m-d explicitly: the plain 'date' cast writes "2026-08-01 00:00:00" into a DATE
        // column, which MySQL silently trims and SQLite does not — so updateOrCreate looking
        // up "2026-08-01" missed its own row and hit the unique index instead.
        'effective_from' => 'date:Y-m-d',
    ];

    /** Monday to Friday, in ISO weekday numbers. */
    public const DEFAULT_WORKING_DAYS = [1, 2, 3, 4, 5];

    public const DEFAULT_HOURS_PER_DAY = 6.0;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function workingDays(): array
    {
        return $this->working_days ?: self::DEFAULT_WORKING_DAYS;
    }
}
