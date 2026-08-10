<?php

namespace Tests\Unit;

use App\Actions\Project\CreateProject;
use App\Actions\Task\CreateTask;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Models\User;
use App\Services\TaskPositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPositionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_sparse_positions_between_neighbors(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, ['name' => 'Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1]);
        $project = app(CreateProject::class)->handle($workspace, $owner, ['name' => 'Web', 'description' => null, 'color' => null, 'status' => ProjectStatus::ACTIVE->value, 'start_date' => null, 'due_date' => null]);
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $payload = fn (string $title) => ['title' => $title, 'description' => null, 'status_public_id' => $status->public_id, 'category_public_id' => null, 'priority' => TaskPriority::MEDIUM->value, 'start_at' => null, 'due_at' => null, 'estimate_minutes' => null, 'assignee_public_ids' => []];
        $first = app(CreateTask::class)->handle($project, $owner, $payload('First'));
        $second = app(CreateTask::class)->handle($project, $owner, $payload('Second'));
        $moving = app(CreateTask::class)->handle($project, $owner, $payload('Moving'));

        $position = app(TaskPositionService::class)->positionFor($moving, $status, $first->public_id, $second->public_id);

        $this->assertSame(1536, $position);
    }
}
