<?php

namespace Tests\Feature\Calendar;

use App\Actions\Project\CreateProject;
use App\Actions\Task\CreateTask;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarScheduleFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_create_reports_attendee_overlap_and_uses_workspace_timezone(): void
    {
        [$owner, $workspace, $project] = $this->project('Asia/Jakarta');

        $first = $this->actingAs($owner)->postJson(route('internal.schedule-events.store', $workspace), [
            ...$this->payload($project->public_id, [$owner->public_id]),
            'title' => 'Planning',
        ])->assertCreated()->assertJsonCount(0, 'data.conflicts');

        $this->assertSame('2026-08-03 02:00:00', ScheduleEvent::where('public_id', $first->json('data.event.public_id'))->firstOrFail()->start_at->utc()->format('Y-m-d H:i:s'));

        $this->actingAs($owner)->postJson(route('internal.schedule-events.store', $workspace), [
            ...$this->payload($project->public_id, [$owner->public_id]),
            'title' => 'Design review',
            'start_at' => '2026-08-03 09:30:00',
            'end_at' => '2026-08-03 10:30:00',
        ])->assertCreated()->assertJsonPath('data.conflicts.0.title', 'Planning');
    }

    public function test_event_validation_rejects_bad_range_foreign_project_and_attendee(): void
    {
        [$owner, $workspace] = $this->project();
        [, , $otherProject] = $this->project();
        $outsider = User::factory()->create();

        $this->actingAs($owner)->postJson(route('internal.schedule-events.store', $workspace), [
            ...$this->payload($otherProject->public_id, [$outsider->public_id]),
            'end_at' => '2026-08-03 08:00:00',
        ])->assertUnprocessable()->assertJsonValidationErrors(['end_at', 'project_public_id', 'attendee_public_ids.0']);
    }

    public function test_event_reschedule_uses_optimistic_version_and_can_be_deleted(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $response = $this->actingAs($owner)->postJson(route('internal.schedule-events.store', $workspace), $this->payload($project->public_id, [$owner->public_id]))->assertCreated();
        $event = ScheduleEvent::where('public_id', $response->json('data.event.public_id'))->firstOrFail();
        $payload = [...$this->payload($project->public_id, [$owner->public_id]), 'title' => 'Moved meeting', 'start_at' => '2026-08-04 11:00:00', 'end_at' => '2026-08-04 12:00:00', 'version' => 1];

        $this->actingAs($owner)->patchJson(route('internal.schedule-events.update', $event), $payload)
            ->assertOk()->assertJsonPath('data.event.version', 2);
        $this->actingAs($owner)->patchJson(route('internal.schedule-events.update', $event), $payload)
            ->assertStatus(409)->assertJsonPath('server_version', 2);
        $this->actingAs($owner)->deleteJson(route('internal.schedule-events.destroy', $event->fresh()))->assertOk();
        $this->assertSoftDeleted('schedule_events', ['id' => $event->id]);
    }

    public function test_calendar_range_returns_the_same_task_and_project_event(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        app(CreateTask::class)->handle($project, $owner, [
            'title' => 'Calendar task', 'description' => null, 'status_public_id' => $status->public_id,
            'category_public_id' => null, 'priority' => TaskPriority::HIGH->value,
            'start_at' => '2026-08-05 09:00:00', 'due_at' => '2026-08-05 11:00:00',
            'estimate_minutes' => 120, 'assignee_public_ids' => [],
        ]);
        $this->actingAs($owner)->postJson(route('internal.schedule-events.store', $workspace), [
            ...$this->payload($project->public_id, []), 'title' => 'Calendar event',
        ])->assertCreated();

        $this->actingAs($owner)->getJson(route('internal.calendar.index', [
            'workspace' => $workspace, 'start' => '2026-08-01', 'end' => '2026-09-01', 'project_public_id' => $project->public_id,
        ]))->assertOk()
            ->assertJsonFragment(['type' => 'task', 'title' => 'Calendar task'])
            ->assertJsonFragment(['type' => 'event', 'title' => 'Calendar event'])
            ->assertJsonPath('meta.timezone', 'UTC');
    }

    public function test_viewer_cannot_mutate_and_outsider_cannot_bind_event(): void
    {
        [$owner, $workspace, $project] = $this->project();
        $viewer = $this->addProjectMember($project, ProjectMemberRole::VIEWER, WorkspaceRole::VIEWER);
        $response = $this->actingAs($owner)->postJson(route('internal.schedule-events.store', $workspace), $this->payload($project->public_id, []))->assertCreated();
        $event = ScheduleEvent::where('public_id', $response->json('data.event.public_id'))->firstOrFail();

        $this->actingAs($viewer)->postJson(route('internal.schedule-events.store', $workspace), $this->payload($project->public_id, []))->assertForbidden();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->patchJson(route('internal.schedule-events.update', $event), [...$this->payload($project->public_id, []), 'version' => 1])->assertNotFound();
    }

    public function test_timeline_and_schedule_pages_render_responsive_controls(): void
    {
        [$owner, $workspace, $project] = $this->project();

        $this->actingAs($owner)->get(route('app.projects.timeline', [$workspace, $project]))
            ->assertOk()->assertSee('Project timeline')->assertSee('Weekly');
        $this->actingAs($owner)->get(route('app.schedule.index', $workspace))
            ->assertOk()->assertSee('Weekly schedule')->assertSee('New event');
    }

    private function payload(?string $projectPublicId, array $attendees): array
    {
        return [
            'title' => 'Weekly sync', 'description' => 'Project coordination.',
            'start_at' => '2026-08-03 09:00:00', 'end_at' => '2026-08-03 10:00:00',
            'project_public_id' => $projectPublicId, 'color' => '#6366f1',
            'meeting_url' => 'https://meet.example.test/weekly', 'attendee_public_ids' => $attendees,
        ];
    }

    private function project(string $timezone = 'UTC'): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, ['name' => fake()->company(), 'timezone' => $timezone, 'locale' => 'en', 'week_start' => 1]);
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => fake()->words(3, true), 'description' => null, 'color' => '#4f46e5',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => null, 'due_date' => null,
        ]);

        return [$owner, $workspace, $project];
    }

    private function addProjectMember($project, ProjectMemberRole $projectRole, WorkspaceRole $workspaceRole): User
    {
        $user = User::factory()->create();
        $project->workspace->memberships()->create([
            'user_id' => $user->id, 'role' => $workspaceRole, 'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);
        $project->memberships()->create(['user_id' => $user->id, 'role' => $projectRole]);

        return $user;
    }
}
