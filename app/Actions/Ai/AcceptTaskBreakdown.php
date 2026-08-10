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

/**
 * The human step PRD-06 exists for. Until this runs, a breakdown is a suggestion; after it,
 * the tasks are real work on someone's board.
 *
 * The reviewer's edits win over the model's numbers — they are the ones being scheduled.
 */
class AcceptTaskBreakdown
{
    public function __construct(private readonly CapacityPlanner $planner) {}

    /**
     * @param  array<int, array{title: string, description: ?string, estimate_minutes: int, checklist: array<int, string>, depends_on: array<int, int>, requires_user_validation: bool, validation_reason: ?string}>  $tasks
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

        $request = $breakdown->subject;
        $project = $request instanceof FeatureRequest ? $request->system : $request->project;

        if (! $project instanceof Project) {
            throw ValidationException::withMessages([
                'breakdown' => 'This request has no project to hold its tasks yet.',
            ]);
        }

        $result = DB::transaction(function () use ($breakdown, $actor, $tasks, $request, $project): array {
            // Accepted first: the planner drops this request's capacity reservation as soon
            // as the breakdown is accepted, which is exactly the room the tasks need.
            $breakdown->update([
                'status' => BreakdownStatus::ACCEPTED,
                'payload_json' => ['tasks' => array_values($tasks)],
                'accepted_at' => now(),
                'accepted_by' => $actor->id,
            ]);

            $assignee = $this->assignee($breakdown, $request, $project);
            $start = $request->scheduled_start
                ? CarbonImmutable::parse($request->scheduled_start)
                : CarbonImmutable::now($breakdown->workspace->timezone ?: 'UTC');

            $dueDates = $this->planner->layOut(
                $breakdown->workspace,
                $assignee,
                array_map(fn (array $task) => (int) $task['estimate_minutes'], $tasks),
                $start,
            );

            $feature = $request instanceof FeatureRequest
                ? $this->feature($request, $project, $start, end($dueDates))
                : null;

            $status = $project->taskStatuses()->active()
                ->where('category', TaskStatusCategory::TODO->value)
                ->orderBy('position')
                ->first()
                ?? $project->taskStatuses()->active()->orderBy('position')->firstOrFail();

            $createdTasks = [];

            foreach ($tasks as $index => $task) {
                $taskStart = $start;
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
                    // Carried onto the task itself: completing it is what opens a checkpoint
                    // (PRD-07), and the payload the reviewer edited is not what the board reads.
                    'requires_user_validation' => (bool) ($task['requires_user_validation'] ?? false),
                    'validation_reason' => $task['validation_reason'] ?? null,
                    'start_at' => $taskStart,
                    'due_at' => $taskDue,
                    'baseline_start_at' => $taskStart,
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

                $dependencyIds = collect($task['depends_on'] ?? [])
                    ->map(fn (int $dependencyIndex) => $createdTasks[$dependencyIndex]->id ?? null)
                    ->filter()
                    ->values();

                if ($dependencyIds->count() !== count($task['depends_on'] ?? [])) {
                    throw ValidationException::withMessages([
                        "tasks.{$index}.depends_on" => 'A prerequisite must be an earlier task in this plan.',
                    ]);
                }

                $created->dependencies()->sync(
                    $dependencyIds->mapWithKeys(fn (int $id) => [$id => ['type' => 'fs', 'lag_minutes' => 0]])->all()
                );
                $createdTasks[$index] = $created;
            }

            $total = array_sum(array_map(fn (array $task) => (int) $task['estimate_minutes'], $tasks));

            $request->forceFill([
                'estimated_minutes' => $total,
                'scheduled_due' => end($dueDates),
                'feature_id' => $feature?->id,
            ])->save();

            // One record for the acceptance, not one per task: the decision is what a reader
            // of this timeline is looking for, and twenty task.created rows bury it.
            ActivityLog::record($breakdown->workspace, $request, 'task_breakdown.accepted', $actor, [
                'tasks' => count($tasks),
                'estimated_minutes' => $total,
                'model' => $breakdown->model,
            ]);

            return [$breakdown->fresh(), $project->id];
        });

        [$accepted] = $result;

        // Deliberately not calling DateShiftService here. CapacityPlanner::layOut has already
        // placed these tasks against the assignee's real free hours, in list order, so the
        // finish-to-start chain is already satisfied. Running the dependency shift on top
        // moved every date again and the two disagreed — the second scheduler won, and the
        // capacity model it overwrote is the one that promises a date the team can meet.
        // "Reschedule from dependencies" stays on the timeline as a deliberate human action.
        return $accepted;
    }

    /**
     * Whoever the queue already picked. A request accepted before the hourly drain reached it
     * has nobody yet, so the same planner picks one here rather than inventing a second rule.
     */
    private function assignee(TaskBreakdown $breakdown, FeatureRequest|ProjectRequest $request, Project $project): User
    {
        $assignee = $request->assignee;

        if (! $assignee) {
            $assignment = $this->planner->assign($breakdown->workspace, $project, (int) ($request->estimated_minutes ?: 60));

            if (! $assignment) {
                throw ValidationException::withMessages([
                    'breakdown' => 'Nobody in this workspace has capacity inside the scheduling horizon.',
                ]);
            }

            $assignee = $assignment['user'];
            $request->forceFill([
                'assignee_id' => $assignee->id,
                'scheduled_start' => $assignment['start'],
                'scheduled_due' => $assignment['due'],
            ])->save();
        }

        // Tasks belong to a board, and a board's policies read project membership.
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
