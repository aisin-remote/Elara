<?php

namespace App\Models;

use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class TaskStatus extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = ['project_id', 'name', 'color', 'category', 'position', 'is_system', 'archived_at'];

    protected $casts = [
        'category' => TaskStatusCategory::class,
        'is_system' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'status_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Seeds a new project from the workspace's status template (master data). The
     * hard-coded set stays as the fallback for a workspace that never edited its template.
     */
    public static function createDefaultsFor(Project $project): void
    {
        $template = TaskStatusTemplate::query()
            ->where('workspace_id', $project->workspace_id)
            ->active()
            ->orderBy('position')
            ->get()
            ->map(fn (TaskStatusTemplate $row) => [$row->name, $row->color, $row->category])
            ->all();

        foreach ($template ?: [
            ['Outstanding', '#6366f1', TaskStatusCategory::TODO],
            ['In Progress', '#f59e0b', TaskStatusCategory::IN_PROGRESS],
            ['Pending', '#94a3b8', TaskStatusCategory::BACKLOG],
            ['Done', '#10b981', TaskStatusCategory::COMPLETED],
        ] as $index => [$name, $color, $category]) {
            $project->taskStatuses()->create([
                'name' => $name,
                'color' => $color,
                'category' => $category,
                'position' => ($index + 1) * 1024,
                'is_system' => true,
            ]);
        }
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (Auth::check()) {
            $query->whereHas('project.workspace.memberships', fn (Builder $membership) => $membership
                ->where('user_id', Auth::id())
                ->where('status', WorkspaceMemberStatus::ACTIVE->value));
        }

        return $query;
    }
}
