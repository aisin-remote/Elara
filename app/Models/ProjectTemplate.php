<?php

namespace App\Models;

use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTemplate extends Model
{
    use GeneratesPublicId;

    protected $fillable = ['workspace_id', 'created_by', 'name', 'task_fields_json', 'statuses_json', 'properties_json'];

    protected $casts = ['task_fields_json' => 'array', 'statuses_json' => 'array', 'properties_json' => 'array'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
