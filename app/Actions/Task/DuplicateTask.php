<?php

namespace App\Actions\Task;

use App\Enums\TaskStatusCategory;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskPositionService;
use Illuminate\Support\Facades\DB;

class DuplicateTask
{
    public function __construct(private readonly TaskPositionService $positions) {}

    public function handle(Task $source, User $actor, ?string $ipAddress = null): Task
    {
        return DB::transaction(function () use ($source, $actor, $ipAddress) {
            $status = $source->project->taskStatuses()->active()
                ->where('category', TaskStatusCategory::BACKLOG->value)
                ->firstOrFail();
            $copy = $source->replicate(['public_id', 'status_id', 'status_changed_at', 'position', 'version', 'completed_at', 'archived_at', 'deleted_at']);
            $copy->fill([
                'title' => 'Copy of '.$source->title,
                'status_id' => $status->id,
                'status_changed_at' => now(),
                'position' => $this->positions->next($status),
                'version' => 1,
                'completed_at' => null,
            ])->save();
            $copy->assignees()->sync($source->assignees->mapWithKeys(fn (User $user) => [$user->id => [
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
            ]])->all());
            $copy->watchers()->sync($source->watchers->pluck('id'));

            foreach ($source->checklistItems as $item) {
                $copy->checklistItems()->create([
                    'title' => $item->title,
                    'is_completed' => false,
                    'position' => $item->position,
                ]);
            }

            ActivityLog::record($source->workspace, $copy, 'task.duplicated', $actor, ['source_public_id' => $source->public_id], $ipAddress);

            return $copy->load(['status', 'category', 'assignees']);
        });
    }
}
