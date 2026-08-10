<?php

namespace App\Models;

use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Leave, training, anything that takes a person off the board for a stretch of days. */
class CapacityException extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = ['workspace_id', 'user_id', 'starts_on', 'ends_on', 'reason', 'note'];

    // Y-m-d explicitly: see WorkspaceHoliday. These columns are DATE, and the planner
    // compares them against date strings.
    protected $casts = ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
