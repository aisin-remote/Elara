<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskPropertyValue extends Model
{
    protected $fillable = ['task_property_id', 'task_id', 'value_json'];

    protected $casts = ['value_json' => 'json'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(TaskProperty::class, 'task_property_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
