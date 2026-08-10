<?php

namespace App\Actions\Task;

use App\Enums\TaskStatusCategory;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use App\Services\TaskPositionService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTask
{
    public function __construct(
        private readonly TaskPositionService $positions,
        private readonly NotificationPreferenceService $notifications,
    ) {}

    public function handle(Project $project, User $creator, array $data, ?string $ipAddress = null): Task
    {
        $task = DB::transaction(function () use ($project, $creator, $data, $ipAddress) {
            $status = TaskStatus::query()
                ->active()
                ->where('project_id', $project->id)
                ->where('public_id', $data['status_public_id'])
                ->first();
            if (! $status) {
                throw ValidationException::withMessages(['status_public_id' => 'Choose a status from this project.']);
            }
            $categoryId = $this->categoryId($project, $data['category_public_id'] ?? null);
            $milestoneId = $this->milestoneId($project, $data['milestone_public_id'] ?? null);
            $task = $project->tasks()->create([
                ...Arr::except($data, ['status_public_id', 'category_public_id', 'milestone_public_id', 'assignee_public_ids', 'attachments']),
                'workspace_id' => $project->workspace_id,
                'status_id' => $status->id,
                'category_id' => $categoryId,
                'milestone_id' => $milestoneId,
                'creator_id' => $creator->id,
                'position' => $this->positions->next($status),
                'status_changed_at' => now(),
                'completed_at' => $status->category === TaskStatusCategory::COMPLETED ? now() : null,
            ]);

            $task->assignees()->sync($this->assigneePayload($project, $creator, $data['assignee_public_ids'] ?? []));
            $task->watchers()->syncWithoutDetaching([$creator->id]);
            ActivityLog::record($project->workspace, $task, 'task.created', $creator, ipAddress: $ipAddress);

            return $task->load(['status', 'category', 'milestone', 'assignees', 'dependencies']);
        });

        $task->assignees->where('id', '!=', $creator->id)->each(fn (User $assignee) => $this->notifications->notify(
            $assignee,
            $project->workspace,
            'task_assigned',
            'Task assigned to you',
            $creator->name.' assigned “'.$task->title.'” to you.',
            route('app.tasks.show', $task),
            ['task_public_id' => $task->public_id],
        ));

        return $task;
    }

    public function assigneePayload(Project $project, User $actor, array $publicIds): array
    {
        $users = $project->members()
            ->whereIn('users.public_id', array_values(array_unique($publicIds)))
            ->pluck('users.id');

        if ($users->count() !== count(array_unique($publicIds))) {
            throw ValidationException::withMessages(['assignee_public_ids' => 'Every assignee must be an active project member.']);
        }

        return $users->mapWithKeys(fn (int $userId) => [$userId => [
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
        ]])->all();
    }

    public function milestoneId(Project $project, ?string $publicId): ?int
    {
        if (! $publicId) {
            return null;
        }

        return ProjectMilestone::query()
            ->where('project_id', $project->id)
            ->where('public_id', $publicId)
            ->value('id') ?? throw ValidationException::withMessages(['milestone_public_id' => 'Choose a milestone from this project.']);
    }

    private function categoryId(Project $project, ?string $publicId): ?int
    {
        if (! $publicId) {
            return null;
        }

        return TaskCategory::query()
            ->where('workspace_id', $project->workspace_id)
            ->where('public_id', $publicId)
            ->value('id') ?? throw ValidationException::withMessages(['category_public_id' => 'Choose a category from this workspace.']);
    }
}
