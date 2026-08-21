<?php

namespace App\Models;

use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscussionComment extends Model
{
    use GeneratesPublicId, SoftDeletes;

    protected $fillable = ['workspace_id', 'subject_type', 'subject_id', 'author_id', 'parent_id', 'body', 'mentions_json', 'pinned_at', 'pinned_by'];

    protected $casts = ['mentions_json' => 'array', 'pinned_at' => 'datetime'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function files(): MorphMany
    {
        return $this->morphMany(ProjectFile::class, 'attachable');
    }

    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }
}
