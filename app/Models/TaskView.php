<?php

namespace App\Models;

use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskView extends Model
{
    use GeneratesPublicId;

    protected $fillable = ['workspace_id', 'project_id', 'user_id', 'name', 'parameters_json', 'is_default'];

    protected $casts = ['parameters_json' => 'array', 'is_default' => 'boolean'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
