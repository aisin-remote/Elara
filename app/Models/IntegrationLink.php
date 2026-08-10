<?php

namespace App\Models;

use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationLink extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = ['connection_id', 'workspace_id', 'project_id', 'task_id', 'schedule_event_id', 'resource_type', 'external_id', 'name', 'url', 'metadata_json'];

    protected $casts = ['metadata_json' => 'array'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
