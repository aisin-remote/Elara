<?php

namespace Tests\Feature\Schedule;

use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectMemberRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\CapacityException;
use App\Models\MemberCapacity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceHoliday;
use App\Services\CapacityPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapacityPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Monday 2026-08-03, so weekday arithmetic in every case below is readable.
        $this->travelTo(CarbonImmutable::parse('2026-08-03 09:00:00', 'UTC'));
    }

    public function test_an_empty_person_starts_today_and_finishes_within_their_daily_hours(): void
    {
        [$workspace, $user] = $this->member();

        // Six hours a day by default, so a six-hour block is exactly one day.
        $slot = $this->planner()->availableFrom($workspace, $user, 360);

        $this->assertSame('2026-08-03', $slot['start']->format('Y-m-d'));
        $this->assertSame('2026-08-03', $slot['due']->format('Y-m-d'));
    }

    public function test_work_larger_than_one_day_spills_across_working_days_only(): void
    {
        [$workspace, $user] = $this->member();

        // 18 hours at six a day: Mon, Tue, Wed.
        $slot = $this->planner()->availableFrom($workspace, $user, 18 * 60);

        $this->assertSame('2026-08-03', $slot['start']->format('Y-m-d'));
        $this->assertSame('2026-08-05', $slot['due']->format('Y-m-d'));
    }

    public function test_a_weekend_is_skipped_rather_than_worked_through(): void
    {
        [$workspace, $user] = $this->member();

        // 30 hours from Monday: Mon–Fri is exactly five days, ending Friday.
        $friday = $this->planner()->availableFrom($workspace, $user, 30 * 60);
        $this->assertSame('2026-08-07', $friday['due']->format('Y-m-d'));

        // One more day of work has to land on the following Monday, not Saturday.
        $monday = $this->planner()->availableFrom($workspace, $user, 36 * 60);
        $this->assertSame('2026-08-10', $monday['due']->format('Y-m-d'));
    }

    public function test_a_partially_booked_day_offers_only_what_is_left(): void
    {
        [$workspace, $user, $system] = $this->memberWithSystem();

        // Four of Monday's six hours are already promised.
        $this->task($system, $user, 4 * 60, '2026-08-03');

        $slot = $this->planner()->availableFrom($workspace, $user, 3 * 60);

        // Two hours fit on Monday, the third rolls into Tuesday.
        $this->assertSame('2026-08-03', $slot['start']->format('Y-m-d'));
        $this->assertSame('2026-08-04', $slot['due']->format('Y-m-d'));
    }

    public function test_a_fully_booked_day_is_stepped_over_rather_than_restarting_the_block(): void
    {
        [$workspace, $user, $system] = $this->memberWithSystem();
        $this->task($system, $user, 6 * 60, '2026-08-04');

        // Tuesday is full, so the twelve hours land on Monday and Wednesday. A full day is
        // treated like a weekend: it contributes nothing and the block continues past it.
        $slot = $this->planner()->availableFrom($workspace, $user, 12 * 60);

        $this->assertSame('2026-08-03', $slot['start']->format('Y-m-d'));
        $this->assertSame('2026-08-05', $slot['due']->format('Y-m-d'));
    }

    public function test_meetings_are_subtracted_as_overhead(): void
    {
        [$workspace, $user] = $this->member();
        $event = $workspace->scheduleEvents()->create([
            'creator_id' => $user->id,
            'title' => 'Planning',
            'start_at' => CarbonImmutable::parse('2026-08-03 10:00:00', 'UTC'),
            'end_at' => CarbonImmutable::parse('2026-08-03 14:00:00', 'UTC'),
            'timezone' => 'UTC',
        ]);
        $event->attendees()->attach($user->id);

        // Four hours of meetings leaves two of six on Monday.
        $slot = $this->planner()->availableFrom($workspace, $user, 2 * 60);
        $this->assertSame('2026-08-03', $slot['due']->format('Y-m-d'));

        $spill = $this->planner()->availableFrom($workspace, $user, 3 * 60);
        $this->assertSame('2026-08-04', $spill['due']->format('Y-m-d'));
    }

    public function test_holidays_and_leave_are_skipped(): void
    {
        [$workspace, $user] = $this->member();
        WorkspaceHoliday::create(['workspace_id' => $workspace->id, 'observed_on' => '2026-08-04', 'name' => 'Public holiday']);
        CapacityException::create([
            'workspace_id' => $workspace->id, 'user_id' => $user->id,
            'starts_on' => '2026-08-05', 'ends_on' => '2026-08-06', 'reason' => 'leave',
        ]);

        // Monday works, Tue is a holiday, Wed–Thu is leave, so the second day is Friday.
        $slot = $this->planner()->availableFrom($workspace, $user, 12 * 60);

        $this->assertSame('2026-08-07', $slot['due']->format('Y-m-d'));
    }

    public function test_a_custom_working_week_is_respected(): void
    {
        [$workspace, $user] = $this->member();
        MemberCapacity::create([
            'workspace_id' => $workspace->id, 'user_id' => $user->id,
            'hours_per_day' => 4, 'working_days' => [1, 3], 'effective_from' => '2026-01-01',
        ]);

        // Four hours a day, Mondays and Wednesdays only.
        $slot = $this->planner()->availableFrom($workspace, $user, 8 * 60);

        $this->assertSame('2026-08-03', $slot['start']->format('Y-m-d'));
        $this->assertSame('2026-08-05', $slot['due']->format('Y-m-d'));
    }

    public function test_nothing_is_offered_beyond_the_horizon(): void
    {
        [$workspace, $user] = $this->member();
        $workspace->update(['settings_json' => ['horizon_days' => 7]]);

        // Far more work than a week of capacity: the planner declines rather than inventing
        // a date nobody can meet.
        $this->assertNull($this->planner()->availableFrom($workspace, $user, 200 * 60));
    }

    public function test_the_pic_keeps_the_work_while_their_opening_is_inside_the_grace_window(): void
    {
        [$workspace, $pic, $system] = $this->memberWithSystem();
        $other = $this->addMember($workspace, WorkspaceRole::MEMBER);
        $system->memberships()->create(['user_id' => $other->id, 'role' => ProjectMemberRole::MEMBER]);

        // Fill the PIC's next four working days; the other member is free today.
        foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06'] as $date) {
            $this->task($system, $pic, 6 * 60, $date);
        }

        $assignment = $this->planner()->assign($workspace, $system, 6 * 60);

        $this->assertSame($pic->id, $assignment['user']->id, 'Four days late is well inside the ten-day grace window.');
        $this->assertFalse($assignment['pic_deferred']);
        $this->assertSame('2026-08-07', $assignment['start']->format('Y-m-d'));
    }

    public function test_the_work_moves_on_once_the_pic_is_later_than_the_grace_window(): void
    {
        [$workspace, $pic, $system] = $this->memberWithSystem();
        $other = $this->addMember($workspace, WorkspaceRole::MEMBER);
        $system->memberships()->create(['user_id' => $other->id, 'role' => ProjectMemberRole::MEMBER]);

        // Book the PIC solid for three weeks: far beyond ten days of grace.
        for ($day = 0; $day < 21; $day++) {
            $this->task($system, $pic, 6 * 60, CarbonImmutable::parse('2026-08-03')->addDays($day)->format('Y-m-d'));
        }

        $assignment = $this->planner()->assign($workspace, $system, 6 * 60);

        $this->assertSame($other->id, $assignment['user']->id, 'A queue behind one expert is a bottleneck, not a plan.');
        $this->assertTrue($assignment['pic_deferred']);
    }

    public function test_the_grace_boundary_tips_exactly_at_the_configured_value(): void
    {
        [$workspace, $pic, $system] = $this->memberWithSystem();
        $other = $this->addMember($workspace, WorkspaceRole::MEMBER);
        $system->memberships()->create(['user_id' => $other->id, 'role' => ProjectMemberRole::MEMBER]);
        $workspace->update(['settings_json' => ['pic_grace_days' => 3]]);

        // Block the PIC for exactly three days: 3 <= 3, so the PIC still keeps it.
        foreach (['2026-08-03', '2026-08-04', '2026-08-05'] as $date) {
            $this->task($system, $pic, 6 * 60, $date);
        }
        $this->assertSame($pic->id, $this->planner()->assign($workspace, $system, 6 * 60)['user']->id);

        // One more blocked day pushes the gap to four, past the window.
        $this->task($system, $pic, 6 * 60, '2026-08-06');
        $this->assertSame($other->id, $this->planner()->assign($workspace, $system, 6 * 60)['user']->id);
    }

    public function test_assignment_is_deterministic_and_declines_when_nobody_fits(): void
    {
        [$workspace, $pic, $system] = $this->memberWithSystem();
        $workspace->update(['settings_json' => ['horizon_days' => 7]]);

        $first = $this->planner()->assign($workspace, $system, 6 * 60);
        $second = $this->planner()->assign($workspace, $system, 6 * 60);

        $this->assertSame($first['user']->id, $second['user']->id);
        $this->assertEquals($first['start'], $second['start']);
        $this->assertNull($this->planner()->assign($workspace, $system, 500 * 60));
    }

    private function planner(): CapacityPlanner
    {
        return app(CapacityPlanner::class);
    }

    private function member(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);

        return [$workspace, $owner];
    }

    private function memberWithSystem(): array
    {
        [$workspace, $owner] = $this->member();
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Inventory Core', 'description' => null, 'color' => '#8b5cf6', 'pic_id' => $owner->id,
        ]);

        return [$workspace, $owner, $system];
    }

    private function addMember(Workspace $workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $user->id, 'role' => $role,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);

        return $user;
    }

    private function task(Project $project, User $assignee, int $minutes, string $dueOn): Task
    {
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        $task = $project->tasks()->create([
            'workspace_id' => $project->workspace_id,
            'status_id' => $status->id,
            'creator_id' => $assignee->id,
            'title' => 'Committed work '.$dueOn,
            'priority' => TaskPriority::MEDIUM,
            'estimate_minutes' => $minutes,
            'due_at' => $dueOn.' 17:00:00',
            'position' => 1024,
        ]);

        $task->assignees()->attach($assignee->id, ['assigned_by' => $assignee->id, 'assigned_at' => now()]);

        return $task;
    }
}
