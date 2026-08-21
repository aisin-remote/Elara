<?php

namespace App\Models;

use App\Enums\MeetingMinuteRelation;
use App\Enums\MeetingMinuteStatus;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingMinuteItem extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = [
        'meeting_minute_id', 'content', 'pic_name', 'pic_user_id', 'related_type', 'project_id',
        'feature_id', 'related_name', 'due_date', 'status', 'position',
    ];

    protected $casts = [
        'related_type' => MeetingMinuteRelation::class,
        'status' => MeetingMinuteStatus::class,
        'due_date' => 'date',
    ];

    public function meetingMinute(): BelongsTo
    {
        return $this->belongsTo(MeetingMinute::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
