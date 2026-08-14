<?php

namespace Tests\Feature\Performance;

use App\Actions\Project\CreateProject;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use App\Services\PersonalTaskSpace;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_uses_documented_kpi_formulas_and_workspace_timezone(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00 UTC');
        [$owner, $workspace, $project] = $this->project('Asia/Jakarta');
        $todo = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $progress = $project->taskStatuses()->where('category', TaskStatusCategory::IN_PROGRESS->value)->firstOrFail();
        $completed = $project->taskStatuses()->where('category', TaskStatusCategory::COMPLETED->value)->firstOrFail();

        $this->task($project, $owner, $todo, ['created_at' => '2026-07-01 00:00:00']);
        $this->task($project, $owner, $progress, ['created_at' => '2026-07-10 00:00:00', 'due_at' => '2026-08-10 00:00:00']);
        $this->task($project, $owner, $todo, ['created_at' => '2026-07-10 00:00:00', 'due_at' => '2026-07-20 00:00:00']);
        $this->task($project, $owner, $completed, ['created_at' => '2026-07-01 00:00:00', 'completed_at' => '2026-07-28 18:00:00']);

        $this->actingAs($owner)->getJson(route('internal.dashboard.index', [
            'workspace' => $workspace, 'from' => '2026-07-28', 'to' => '2026-07-29',
        ]))->assertOk()
            ->assertJsonPath('data.period.timezone', 'Asia/Jakarta')
            ->assertJsonPath('data.kpis.total.value', 4)
            ->assertJsonPath('data.kpis.in_progress.value', 1)
            ->assertJsonPath('data.kpis.overdue.value', 1)
            ->assertJsonPath('data.kpis.completed.value', 1);
    }

    public function test_completion_boundary_is_converted_from_workspace_day_to_utc(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00 UTC');
        [$owner, $workspace, $project] = $this->project('Asia/Jakarta');
        $completed = $project->taskStatuses()->where('category', TaskStatusCategory::COMPLETED->value)->firstOrFail();
        $this->task($project, $owner, $completed, ['created_at' => '2026-07-01 00:00:00', 'completed_at' => '2026-07-28 16:59:59']);
        $this->task($project, $owner, $completed, ['created_at' => '2026-07-01 00:00:00', 'completed_at' => '2026-07-28 17:00:00']);

        $this->actingAs($owner)->getJson(route('internal.dashboard.index', [
            'workspace' => $workspace, 'from' => '2026-07-29', 'to' => '2026-07-29',
        ]))->assertOk()->assertJsonPath('data.kpis.completed.value', 1);
    }

    public function test_metrics_and_project_filter_are_permission_aware(): void
    {
        [$owner, $workspace, $visibleProject] = $this->project();
        $hiddenProject = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Hidden project', 'description' => null, 'color' => '#f43f5e',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => null, 'due_date' => null,
        ]);
        $member = $this->addProjectMember($visibleProject);
        $visibleStatus = $visibleProject->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $hiddenStatus = $hiddenProject->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $this->task($visibleProject, $owner, $visibleStatus);
        $this->task($hiddenProject, $owner, $hiddenStatus);

        $this->actingAs($member)->getJson(route('internal.dashboard.index', $workspace))
            ->assertOk()->assertJsonPath('data.kpis.total.value', 1);
        $this->actingAs($member)->getJson(route('internal.performance.index', [
            'workspace' => $workspace, 'project_public_id' => $hiddenProject->public_id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('project_public_id');
    }

    public function test_performance_filters_workload_and_bottleneck_are_real(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00 UTC');
        config(['orbitra.bottleneck_days' => 7]);
        [$owner, $workspace, $project] = $this->project();
        $member = $this->addProjectMember($project);
        $progress = $project->taskStatuses()->where('category', TaskStatusCategory::IN_PROGRESS->value)->firstOrFail();
        $completed = $project->taskStatuses()->where('category', TaskStatusCategory::COMPLETED->value)->firstOrFail();
        $stale = $this->task($project, $owner, $progress, ['created_at' => '2026-07-01 00:00:00', 'updated_at' => '2026-07-10 00:00:00']);
        $done = $this->task($project, $owner, $completed, ['created_at' => '2026-07-20 00:00:00', 'completed_at' => '2026-07-25 00:00:00']);
        $stale->assignees()->attach($member->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);
        $done->assignees()->attach($member->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);

        $this->actingAs($owner)->getJson(route('internal.performance.index', [
            'workspace' => $workspace, 'from' => '2026-07-01', 'to' => '2026-07-29',
            'member_public_id' => $member->public_id, 'status_public_id' => $progress->public_id,
        ]))->assertOk()
            ->assertJsonPath('data.workload.0.name', $member->name)
            ->assertJsonPath('data.workload.0.open', 1)
            ->assertJsonPath('data.bottlenecks.0.public_id', $stale->public_id)
            ->assertJsonPath('data.bottleneck_threshold_days', 7);
    }

    public function test_gantt_uses_real_project_dates_progress_and_permissions(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00 UTC');
        CarbonImmutable::setTestNow('2026-07-29 12:00:00 UTC');
        [$owner, $workspace, $project] = $this->project();
        $project->update(['start_date' => '2026-07-27', 'due_date' => '2026-08-05']);
        $member = $this->addProjectMember($project);
        $todo = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $completed = $project->taskStatuses()->where('category', TaskStatusCategory::COMPLETED->value)->firstOrFail();
        $this->task($project, $owner, $todo);
        $this->task($project, $owner, $completed, ['completed_at' => '2026-07-28 12:00:00']);

        $hiddenProject = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Hidden timeline', 'description' => null, 'color' => '#f43f5e',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => '2026-07-28', 'due_date' => '2026-08-04',
        ]);
        foreach (range(1, 5) as $index) {
            $extraProject = app(CreateProject::class)->handle($workspace, $owner, [
                'name' => "Timeline project {$index}", 'description' => null, 'color' => '#6366f1',
                'status' => ProjectStatus::ACTIVE->value,
                'start_date' => CarbonImmutable::parse('2026-07-27')->addDays($index)->format('Y-m-d'),
                'due_date' => CarbonImmutable::parse('2026-07-30')->addDays($index)->format('Y-m-d'),
            ]);
            $extraProject->memberships()->create(['user_id' => $member->id, 'role' => ProjectMemberRole::MEMBER]);
        }

        $this->actingAs($member)->getJson(route('internal.dashboard.index', [
            'workspace' => $workspace, 'gantt_scale' => 'daily',
        ]))->assertOk()
            ->assertJsonPath('data.gantt.scale', 'daily')
            ->assertJsonCount(5, 'data.gantt.projects')
            ->assertJsonPath('data.gantt.projects.0.public_id', $project->public_id)
            ->assertJsonPath('data.gantt.projects.0.progress', 50)
            ->assertJsonMissing(['public_id' => $hiddenProject->public_id])
            ->assertJsonMissing(['public_id' => $extraProject->public_id]);
    }

    public function test_task_gantt_contains_only_own_personal_and_directly_assigned_work(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00 UTC');
        CarbonImmutable::setTestNow('2026-07-29 12:00:00 UTC');
        [$owner, $workspace, $project] = $this->project();
        $member = $this->addProjectMember($project);
        $todo = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        $assigned = $this->task($project, $owner, $todo, [
            'title' => 'Assigned release task',
            'start_at' => '2026-07-28 09:00:00',
            'due_at' => '2026-07-31 17:00:00',
        ]);
        $assigned->assignees()->attach($member->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);
        $unassigned = $this->task($project, $owner, $todo, [
            'title' => 'Someone elses visible task',
            'due_at' => '2026-07-30 17:00:00',
        ]);

        $personalSpace = app(PersonalTaskSpace::class)->for($workspace, $member);
        $personal = $this->task(
            $personalSpace,
            $member,
            $personalSpace->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail(),
            ['title' => 'Private planning task', 'due_at' => '2026-07-30 12:00:00'],
        );
        $ownerPersonalSpace = app(PersonalTaskSpace::class)->for($workspace, $owner);
        $otherPersonal = $this->task(
            $ownerPersonalSpace,
            $owner,
            $ownerPersonalSpace->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail(),
            ['title' => 'Owners private task', 'due_at' => '2026-07-30 12:00:00'],
        );
        foreach (range(1, 4) as $index) {
            $extraAssigned = $this->task($project, $owner, $todo, [
                'title' => "Extra assigned task {$index}",
                'due_at' => CarbonImmutable::parse('2026-07-30 12:00:00')->addHours($index)->toDateTimeString(),
            ]);
            $extraAssigned->assignees()->attach($member->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);
        }

        $this->actingAs($member)->getJson(route('internal.dashboard.index', [
            'workspace' => $workspace,
            'gantt_view' => 'tasks',
        ]))->assertOk()
            ->assertJsonPath('data.gantt.view', 'tasks')
            ->assertJsonPath('data.gantt.scale', 'weekly')
            ->assertJsonCount(5, 'data.gantt.tasks')
            ->assertJsonFragment(['public_id' => $assigned->public_id, 'context' => $project->name])
            ->assertJsonFragment(['public_id' => $personal->public_id, 'context' => 'Personal task'])
            ->assertJsonMissing(['public_id' => $unassigned->public_id])
            ->assertJsonMissing(['public_id' => $otherPersonal->public_id])
            ->assertJsonMissing(['public_id' => $extraAssigned->public_id]);
    }

    public function test_dashboard_and_performance_pages_render_real_controls(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00 UTC');
        CarbonImmutable::setTestNow('2026-07-29 12:00:00 UTC');
        [$owner, $workspace, $project] = $this->project();
        $project->update(['start_date' => '2026-07-27', 'due_date' => '2026-08-05']);

        $dashboardResponse = $this->actingAs($owner)->get(route('app.workspaces.show', $workspace))
            ->assertOk()
            ->assertSee('Task Performance')
            ->assertSee('New Task')
            ->assertSee('Member task workload')
            ->assertSee('Task distribution')
            ->assertSee('Project timeline')
            ->assertSee('Add task')
            ->assertSee('aria-label="Timeline view"', false)
            ->assertSee('data-timeline-tabs', false)
            ->assertSee('gantt_view=tasks&amp;gantt_scale=weekly', false)
            ->assertSee('gantt_view=projects&amp;gantt_scale=monthly', false)
            ->assertSee(route('app.projects.index', $workspace))
            ->assertSee('xl:grid-cols-3', false)
            ->assertSee('href="'.route('app.projects.show', $project).'" class="sticky left-0', false);
        $this->assertLessThan(
            strpos($dashboardResponse->getContent(), 'id="dashboard-timeline-card"'),
            strpos($dashboardResponse->getContent(), 'data-timeline-tabs'),
        );
        $this->assertLessThan(
            strpos($dashboardResponse->getContent(), 'aria-labelledby="distribution-title"'),
            strpos($dashboardResponse->getContent(), 'aria-labelledby="members-title"'),
        );
        $this->actingAs($owner)->get(route('internal.dashboard.widgets.insights', $workspace))
            ->assertOk()->assertSee('Task Performance')->assertSee('Member task workload')->assertDontSee('Task distribution');
        $this->actingAs($owner)->get(route('app.workspaces.show', ['workspace' => $workspace, 'gantt_view' => 'tasks']))
            ->assertOk()
            ->assertSee('data-gantt-view="tasks"', false)
            ->assertSee('name="gantt_view" value="tasks"', false)
            ->assertSee(route('app.tasks.index', $workspace));
        $this->actingAs($owner)->get(route('app.performance.index', $workspace))
            ->assertOk()->assertSee('Performance overview')->assertSee('Bottleneck warning')->assertSee('PDF report');
    }

    public function test_csv_and_pdf_exports_use_authorized_filtered_data(): void
    {

        [$owner, $workspace, $project] = $this->project();
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $task = $this->task($project, $owner, $status, ['title' => 'Export only this task']);

        $csv = $this->actingAs($owner)->get(route('internal.reports.csv', [
            'workspace' => $workspace, 'project_public_id' => $project->public_id,
        ]))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($task->public_id, $csv->streamedContent());

        $this->actingAs($owner)->get(route('internal.reports.pdf', $workspace))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('internal.reports.csv', $workspace))->assertNotFound();
    }

    public function test_my_meetings_and_member_task_workload_are_permission_aware(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00 UTC');
        [$owner, $workspace, $project] = $this->project();
        $member = $this->addProjectMember($project);
        $todo = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $visibleTask = $this->task($project, $owner, $todo, ['due_at' => '2026-07-29 15:00:00']);
        $visibleTask->assignees()->attach($member->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);

        $hiddenProject = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Hidden workload', 'description' => null, 'color' => '#f43f5e',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => null, 'due_date' => null,
        ]);
        $hiddenStatus = $hiddenProject->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $hiddenTask = $this->task($hiddenProject, $owner, $hiddenStatus, ['due_at' => '2026-07-29 16:00:00']);
        $hiddenTask->assignees()->attach($owner->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);

        $event = ScheduleEvent::factory()->create([
            'workspace_id' => $workspace->id, 'project_id' => $project->id, 'creator_id' => $owner->id,
            'title' => 'Metrics sync', 'start_at' => '2026-07-30 09:00:00', 'end_at' => '2026-07-30 10:00:00',
            'meeting_url' => 'https://meet.example.test/metrics',
        ]);
        $event->attendees()->attach($member->id);

        $this->actingAs($member)->getJson(route('internal.dashboard.index', [
            'workspace' => $workspace, 'from' => '2026-07-29', 'to' => '2026-07-30',
        ]))->assertOk()
            ->assertJsonPath('data.meetings.0.title', 'Metrics sync')
            ->assertJsonPath('data.member_task_heatmap.total_tasks', 1)
            ->assertJsonFragment(['public_id' => $member->public_id, 'open' => 1, 'completed' => 0]);
    }

    private function task($project, User $creator, $status, array $attributes = []): Task
    {
        $task = $project->tasks()->create([
            'workspace_id' => $project->workspace_id,
            'status_id' => $status->id,
            'creator_id' => $creator->id,
            'title' => $attributes['title'] ?? fake()->sentence(4),
            'priority' => TaskPriority::MEDIUM,
            'start_at' => $attributes['start_at'] ?? null,
            'due_at' => $attributes['due_at'] ?? null,
            'completed_at' => $attributes['completed_at'] ?? null,
            'position' => 1024,
        ]);
        Task::query()->whereKey($task->id)->update([
            'created_at' => $attributes['created_at'] ?? now(),
            'updated_at' => $attributes['updated_at'] ?? ($attributes['created_at'] ?? now()),
            'status_changed_at' => $attributes['status_changed_at'] ?? ($attributes['updated_at'] ?? ($attributes['created_at'] ?? now())),
        ]);

        return $task->fresh();
    }

    public function test_dashboard_focus_list_flags_stale_tasks_and_activity_links_to_its_subject(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00 UTC');
        [$owner, $workspace, $project] = $this->project();
        $todo = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        $stale = $this->task($project, $owner, $todo, ['title' => 'Ship the migration', 'due_at' => '2026-07-24 09:00:00']);
        $stale->assignees()->attach($owner->id, ['assigned_by' => $owner->id, 'assigned_at' => now()]);
        ActivityLog::record($workspace, $stale, 'task.created', $owner);

        $response = $this->actingAs($owner)->get(route('app.workspaces.show', $workspace))->assertOk();

        // The list also carries unfinished tasks from earlier days, so the card must say why.
        $response->assertSee('Overdue since Jul 24')->assertSee('1 still overdue');
        $response->assertSee(route('app.tasks.show', $stale));
        $response->assertSee('task created');
    }

    public function test_dashboard_focus_list_is_limited_to_two_tasks(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00 UTC');

        [$owner, $workspace, $project] = $this->project();
        $todo = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        foreach (range(1, 3) as $index) {
            $task = $this->task($project, $owner, $todo, [
                'title' => "Focus task {$index}",
                'due_at' => '2026-07-29 15:00:00',
            ]);

            $task->assignees()->attach($owner->id, [
                'assigned_by' => $owner->id,
                'assigned_at' => now(),
            ]);
        }

        $response = $this->actingAs($owner)
            ->getJson(route('internal.dashboard.index', $workspace))
            ->assertOk();

        $this->assertCount(2, $response->json('data.today_tasks'));
    }

    public function test_dashboard_activity_keeps_only_the_three_newest_entries(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $todo = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        foreach (range(1, 5) as $index) {
            ActivityLog::record($workspace, $this->task($project, $owner, $todo), 'task.created', $owner);
        }

        $this->actingAs($owner)->getJson(route('internal.dashboard.index', ['workspace' => $workspace]))
            ->assertOk()
            ->assertJsonCount(3, 'data.recent_activity');
    }

    private function project(string $timezone = 'UTC'): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => fake()->company(), 'timezone' => $timezone, 'locale' => 'en', 'week_start' => 1,
        ]);
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => fake()->words(3, true), 'description' => null, 'color' => '#2eb0fb',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => null, 'due_date' => null,
        ]);

        return [$owner, $workspace, $project];
    }

    private function addProjectMember($project): User
    {
        $member = User::factory()->create();
        $project->workspace->memberships()->create([
            'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);
        $project->memberships()->create(['user_id' => $member->id, 'role' => ProjectMemberRole::MEMBER]);

        return $member;
    }
}
