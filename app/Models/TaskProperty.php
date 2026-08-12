<?php

namespace App\Models;

use App\Enums\TaskPropertyType;
use App\Enums\WorkspaceMemberStatus;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class TaskProperty extends Model
{
    use GeneratesPublicId;

    protected $fillable = ['project_id', 'name', 'type', 'options_json', 'position', 'archived_at'];

    protected $casts = [
        'type' => TaskPropertyType::class,
        'options_json' => 'array',
        'archived_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(TaskPropertyValue::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function normalizeInputValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($this->type) {
            TaskPropertyType::TEXT => trim((string) $value),
            TaskPropertyType::SELECT => (string) $value,
            TaskPropertyType::CHECKBOX => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        };
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field)->whereNull('archived_at');

        if (Auth::check()) {
            $query->whereHas('project.workspace.memberships', fn (Builder $membership) => $membership
                ->where('user_id', Auth::id())
                ->where('status', WorkspaceMemberStatus::ACTIVE->value));
        }

        return $query;
    }
}
