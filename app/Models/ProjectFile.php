<?php

namespace App\Models;

use App\Enums\WorkspaceMemberStatus;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class ProjectFile extends Model
{
    use GeneratesPublicId, HasFactory, SoftDeletes;

    protected $table = 'files';

    protected $fillable = [
        'workspace_id', 'project_id', 'task_id', 'uploader_id', 'disk', 'path', 'original_name',
        'mime_type', 'size', 'metadata_json', 'attachable_type', 'attachable_id',
    ];

    protected $casts = ['metadata_json' => 'array'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** The request this file was attached to, when it belongs to one rather than to a board. */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(Message::class, 'message_attachments', 'file_id', 'message_id');
    }

    public function isPreviewable(): bool
    {
        return str_starts_with($this->mime_type, 'image/') || $this->mime_type === 'application/pdf';
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (Auth::check()) {
            $query->whereHas('workspace.memberships', fn (Builder $membership) => $membership
                ->where('user_id', Auth::id())
                ->where('status', WorkspaceMemberStatus::ACTIVE->value));
        }

        return $query;
    }
}
