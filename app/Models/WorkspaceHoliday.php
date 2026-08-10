<?php

namespace App\Models;

use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceHoliday extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = ['workspace_id', 'observed_on', 'name'];

    // Y-m-d explicitly: a plain 'date' cast writes a time component into a DATE column, and
    // then a lookup by "2026-08-12" no longer finds the row it just wrote.
    protected $casts = ['observed_on' => 'date:Y-m-d'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
