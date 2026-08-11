<?php

namespace App\Actions\Ai;

use App\Enums\BreakdownStatus;
use App\Enums\ProjectMemberRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Models\ActivityLog;
use App\Models\Feature;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\Task;
use App\Models\TaskBreakdown;
use App\Models\User;
use App\Services\CapacityPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Converts a reviewed AI draft into real board tasks. */
class AcceptTaskBreakdown
{
    public function __construct(private readonly CapacityPlanner $planner) {}

    /**
     * @param  array<int, array{title: string, description: ?string, estimate_minutes: int, checklist: array<int, string>, requires_user_validation: bool, validation_reason: ?string}>  $tasks
     */
    public function handle(TaskBreakdown $breakdown, User $actor, array $tasks): TaskBreakdown
    {
        if ($breakdown->status === BreakdownStatus::ACCEPTED) {
            throw ValidationException::withMessages([
                'breakdown' => 'This breakdown has already been accepted.',
            ]);
        }

        if ($tasks === []) {
            throw ValidationException::withMessages([
                'tasks' => 'Accepting an empty plan would create no work. Discard it instead.',
            ]);
        }

        $subject = $breakdown->subject;
        $project = match (true) {
            $subject instanceof FeatureRequest => $subject->system,
            $subject instanceof ProjectRequest => $subject->project,
            $subject instanceof Feature => $subject->project,
            $subject instanceof Project => $subject,
            default => null,
        };

        if (! $project instanceof Project) {
            throw ValidationException::withMessages([
                'breakdown' => 'This plan has no project to hold its tasks.',
            ]);
        }

        return DB::transaction(function () use ($breakdown, $actor, $tasks, $subject, $project): TaskBreakdown {
            $breakdown->update([
                'status' => BreakdownStatus::ACCEPTED,
                'payload_json' => ['tasks' => array_values($tasks)],
                'accepted_at' => now(),
                'accepted_by' => $actor->id,
            ]);

            $total = array_sum(array_map(fn (array $task) => (int) $task['estimate_minutes'], $tasks));
            $assignee = $this->assignee($breakdown, $subject, $project, $total);
            $startValue = match (true) {
                $subject instanceof FeatureRequest, $subject instanceof ProjectRequest => $subject->scheduled_start,
                $subject instanceof Feature => $subject->starts_at,
                $subject instanceof Project => $subject->start_date,
                default => null,
            };
            $start = $startValue
                ? CarbonImmutable::parse($startValue)
                : CarbonImmutable::now($breakdown->workspace->timezone ?: 'UTC');

            $dueDates = $this->planner->layOut(
                $breakdown->workspace,
                $assignee,
                array_map(fn (array $task) => (int) $task['estimate_minutes'], $tasks),
                $start,
            );

            $feature = match (true) {
                $subject instanceof Feature => $subject,
                $subject instanceof FeatureRequest => $this->feature($subject, $project, $start, end($dueDates)),
                default => null,
            };

            $status = $project->taskStatuses()->active()
                ->where('category', TaskStatusCategory::TODO->value)
                ->orderBy('position')
                ->first()
                ?? $project->taskStatuses()->active()->orderBy('position')->firstOrFail();

            $requesterOwned = $subject instanceof FeatureRequest || $subject instanceof ProjectRequest;

            foreach ($tasks as $index => $task) {
                $taskDue = $dueDates[$index]->setTime(17, 0);
                $created = Task::create([
                    'workspace_id' => $breakdown->workspace_id,
                    'project_id' => $project->id,
                    'feature_id' => $feature?->id,
                    'status_id' => $status->id,
                    'creator_id' => $actor->id,
                    'title' => $task['title'],
                    'description' => $task['description'] ?? null,
                    'priority' => TaskPriority::MEDIUM,
                    'estimate_minutes' => (int) $task['estimate_minutes'],
                    'requires_user_validation' => $requesterOwned && (bool) ($task['requires_user_validation'] ?? false),
                    'validation_reason' => $requesterOwned ? ($task['validation_reason'] ?? null) : null,
                    'start_at' => $start,
                    'due_at' => $taskDue,
                    'baseline_start_at' => $start,
                    'baseline_due_at' => $taskDue,
                    'status_changed_at' => now(),
                    'position' => ($index + 1) * 1024,
                ]);

                $created->assignees()->attach($assignee->id, [
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                ]);

                foreach ($task['checklist'] ?? [] as $checklistIndex => $title) {
                    $created->checklistItems()->create([
                        'title' => $title,
                        'position' => ($checklistIndex + 1) * 1024,
                    ]);
                }
            }

            $finish = end($dueDates);

            if ($subject instanceof FeatureRequest) {
                $subject->forceFill([
                    'estimated_minutes' => $total,
                    'scheduled_due' => $finish,
                    'feature_id' => $feature?->id,
                ])->save();
            } elseif ($subject instanceof ProjectRequest) {
                $subject->forceFill([
                    'estimated_minutes' => $total,
                    'scheduled_due' => $finish,
                ])->save();
            } elseif ($subject instanceof Feature) {
                $subject->forceFill([
                    'starts_at' => $subject->starts_at ?: $start,
                    'due_at' => $subject->due_at ?: $finish,
                ])->save();
            } elseif ($subject instanceof Project) {
                $subject->forceFill([
                    'start_date' => $subject->start_date ?: $start,
                    'due_date' => $subject->due_date ?: $finish,
                ])->save();
            }

            ActivityLog::record($breakdown->workspace, $subject, 'task_breakdown.accepted', $actor, [
                'tasks' => count($tasks),
                'estimated_minutes' => $total,
                'model' => $breakdown->model,
            ]);

            return $breakdown->fresh();
        });
    }

    private function assignee(
        TaskBreakdown $breakdown,
        FeatureRequest|ProjectRequest|Feature|Project $subject,
        Project $project,
        int $totalMinutes,
    ): User {
        $assignee = match (true) {
            $subject instanceof FeatureRequest, $subject instanceof ProjectRequest => $subject->assignee,
            $subject instanceof Feature => $project->pic(),
            $subject instanceof Project => $subject->owner,
        };

        if (! $assignee) {
            $assignment = $this->planner->assign($breakdown->workspace, $project, max(60, $totalMinutes));

            if (! $assignment) {
                throw ValidationException::withMessages([
                    'breakdown' => 'Nobody in this workspace has capacity inside the scheduling horizon.',
                ]);
            }

            $assignee = $assignment['user'];

            if ($subject instanceof FeatureRequest || $subject instanceof ProjectRequest) {
                $subject->forceFill([
                    'assignee_id' => $assignee->id,
                    'scheduled_start' => $assignment['start'],
                    'scheduled_due' => $assignment['due'],
                ])->save();
            }
        }

        $project->memberships()->firstOrCreate(
            ['user_id' => $assignee->id],
            ['role' => ProjectMemberRole::MEMBER],
        );

        return $assignee;
    }

    private function feature(FeatureRequest $request, Project $project, CarbonImmutable $start, CarbonImmutable $due): Feature
    {
        return Feature::updateOrCreate(
            ['workspace_id' => $request->workspace_id, 'project_id' => $project->id, 'name' => $request->title],
            [
                'description' => $request->desired_outcome,
                'starts_at' => $start,
                'due_at' => $due,
            ],
        );
    }
}
