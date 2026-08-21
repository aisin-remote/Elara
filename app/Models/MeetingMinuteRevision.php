<?php

namespace App\Models;

use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingMinuteRevision extends Model
{
    use GeneratesPublicId;

    public $timestamps = false;

    protected $fillable = ['meeting_minute_id', 'editor_id', 'revision', 'snapshot_json', 'created_at'];

    protected $casts = ['snapshot_json' => 'array', 'created_at' => 'datetime'];

    public function meetingMinute(): BelongsTo
    {
        return $this->belongsTo(MeetingMinute::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
