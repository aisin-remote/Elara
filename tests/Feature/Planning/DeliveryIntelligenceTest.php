<?php

namespace Tests\Feature\Planning;

use App\Actions\Project\CreateProject;
use App\Actions\Task\CreateTask;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\DependencyType;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Models\DeliveryInsight;
use App\Models\Task;
use App\Models\User;
use App\Services\Planning\CriticalPathAnalyzer;
use App\Services\Planning\DateShiftService;
use App\Services\Planning\ForecastHealthService;
use App\Services\Planning\WeeklyInsightsGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_critical_path_marks_the_longest_dependency_chain(): void
    {
        [$owner, , $project] = $this->project();
        $design = $this->task($project, $owner, 'Design', estimate: 480);
        $build = $this->task($project, $owner, 'Build', estimate: 960);
        $ship = $this->task($project, $owner, 'Ship', estimate: 480);
        $docs = $this->task($project, $owner, 'Docs', estimate: 240);

        $build->dependencies()->attach($design->id, ['type' => 'fs', 'lag_minutes' => 0]);
        $ship->dependencies()->attach($build->id, ['type' => 'fs', 'lag_minutes' => 0]);
        $docs->dependencies()->attach($design->id, ['type' => 'fs', 'lag_minutes' => 0]);

        $path = app(CriticalPathAnalyzer::class)->forProject($project->fresh());
        $critical = collect($path['tasks'])->where('is_critical', true)->pluck('title')->all();

        $this->assertContains('Design', $critical);
        $this->assertContains('Build', $critical);
        $this->assertContains('Ship', $critical);
        $this->assertNotContains('Docs', $critical);
        $this->assertGreaterThan(0, $path['project_duration_days']);
    }

    public function test_adding_dependency_pushes_dependent_dates_and_allows_cross_project_links(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        [$owner, $workspace, $project] = $this->project();
        $other = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Sibling project', 'description' => null, 'color' => '#0ea5e9',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => '2026-08-01', 'due_date' => '2026-09-30',
        ]);

        $prerequisite = $this->task($other, $owner, 'Approve API', '2026-08-12 17:00:00', '2026-08-11 09:00:00', 480);
        $dependent = $this->task($project, $owner, 'Build API', '2026-08-11 17:00:00', '2026-08-10 09:00:00', 480);
        $dependent->assignees()->attach($owner->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);

        $this->actingAs($owner)->postJson(route('internal.task-dependencies.store', $dependent), [
            'dependency_public_id' => $prerequisite->public_id,
            'type' => DependencyType::FINISH_TO_START->value,
            'lag_minutes' => 0,
        ])->assertCreated()->assertJsonPath('data.is_blocked', true);

        $dependent->refresh();
        $this->assertTrue($dependent->due_at->greaterThan(Carbon::parse('2026-08-12 17:00:00')));
        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $dependent->id,
            'depends_on_task_id' => $prerequisite->id,
            'type' => 'fs',
        ]);

        Carbon::setTestNow();
    }

    public function test_foreign_workspace_dependency_is_rejected(): void
    {
        [$owner, , $project] = $this->project();
        $foreignOwner = User::factory()->create();
        [, , $foreignProject] = $this->project($foreignOwner);
        $local = $this->task($project, $owner, 'Local');
        $foreign = $this->task($foreignProject, $foreignOwner, 'Foreign');

        $this->actingAs($owner)->postJson(route('internal.task-dependencies.store', $local), [
            'dependency_public_id' => $foreign->public_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('dependency_public_id');
    }

    public function test_baseline_reschedule_time_tracking_portfolio_and_weekly_insights(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        [$owner, $workspace, $project] = $this->project();
        $task = $this->task($project, $owner, 'Implement', '2026-08-14 17:00:00', '2026-08-11 09:00:00', 480);
        $task->assignees()->attach($owner->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);

        $this->actingAs($owner)->postJson(route('internal.projects.baseline', $project))
            ->assertOk()
            ->assertJsonPath('data.tasks', 1);

        $this->assertNotNull($task->fresh()->baseline_due_at);

        $prerequisite = $this->task($project, $owner, 'Spec', '2026-08-20 17:00:00', '2026-08-18 09:00:00', 480);
        $task->dependencies()->attach($prerequisite->id, ['type' => 'fs', 'lag_minutes' => 0]);

        $this->actingAs($owner)->postJson(route('internal.projects.reschedule', $project))
            ->assertOk();

        $this->assertTrue($task->fresh()->start_at->greaterThanOrEqualTo(Carbon::parse('2026-08-21 00:00:00')));

        $this->actingAs($owner)->postJson(route('internal.task-time-entries.store', $task), [
            'minutes' => 90,
            'worked_on' => '2026-08-10',
            'note' => 'Spike',
        ])->assertCreated()->assertJsonPath('data.logged_minutes', 90);

        $forecast = app(ForecastHealthService::class)->forProject($project->fresh());
        $this->assertContains($forecast['state'], ['on_track', 'at_risk', 'late', 'complete']);

        $this->actingAs($owner)->get(route('app.portfolio.index', $workspace))
            ->assertOk()
            ->assertSee('Delivery portfolio')
            ->assertSee($project->name);

        $this->actingAs($owner)->get(route('app.projects.timeline', [$workspace, $project]))
            ->assertOk()
            ->assertSee('Capture baseline')
            ->assertSee('Reschedule from dependencies');

        $insight = app(WeeklyInsightsGenerator::class)->generate($workspace);
        $this->assertInstanceOf(DeliveryInsight::class, $insight);
        $this->assertSame('rules', $insight->source);
        $this->assertSame(1, DeliveryInsight::query()->count());

        // Idempotent for the same week.
        app(WeeklyInsightsGenerator::class)->generate($workspace);
        $this->assertSame(1, DeliveryInsight::query()->count());

        $this->artisan('orbitra:generate-weekly-insights')->assertSuccessful();

        Carbon::setTestNow();
    }

    public function test_date_shift_service_never_pulls_dates_earlier(): void
    {
        Carbon::setTestNow('2026-08-03 09:00:00');
        [$owner, , $project] = $this->project();
        $prerequisite = $this->task($project, $owner, 'A', '2026-08-04 17:00:00', '2026-08-03 09:00:00', 240);
        $dependent = $this->task($project, $owner, 'B', '2026-08-30 17:00:00', '2026-08-20 09:00:00', 240);
        $dependent->assignees()->attach($owner->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);
        $dependent->dependencies()->attach($prerequisite->id, ['type' => 'fs', 'lag_minutes' => 0]);

        $before = $dependent->fresh()->due_at->copy();
        app(DateShiftService::class)->shiftProject($project, $owner);
        $this->assertTrue($dependent->fresh()->due_at->equalTo($before));

        Carbon::setTestNow();
    }

    private function project(?User $owner = null): array
    {
        $owner ??= User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Orbitra Planning',
            'timezone' => 'UTC',
            'locale' => 'en',
            'week_start' => 1,
        ]);
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Delivery Intelligence', 'description' => null, 'color' => '#6366f1',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => '2026-08-01', 'due_date' => '2026-08-31',
        ]);

        return [$owner, $workspace, $project];
    }

    private function task($project, User $creator, string $title, ?string $dueAt = null, ?string $startAt = null, int $estimate = 240): Task
    {
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        return app(CreateTask::class)->handle($project, $creator, [
            'title' => $title,
            'description' => 'Phase 16B coverage.',
            'status_public_id' => $status->public_id,
            'category_public_id' => null,
            'milestone_public_id' => null,
            'priority' => TaskPriority::HIGH->value,
            'start_at' => $startAt,
            'due_at' => $dueAt,
            'estimate_minutes' => $estimate,
            'assignee_public_ids' => [],
        ]);
    }
}
