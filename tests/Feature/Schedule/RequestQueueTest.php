<?php

namespace Tests\Feature\Schedule;

use App\Actions\Project\CreateSystem;
use App\Actions\Request\ScheduleApprovedRequests;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\FeatureRequestStatus;
use App\Enums\RequestUrgency;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RequestQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // Monday 2026-08-03, matching CapacityPlannerTest so dates below read plainly.
        $this->travelTo(CarbonImmutable::parse('2026-08-03 09:00:00', 'UTC'));
    }

    public function test_the_queue_drains_oldest_approval_first(): void
    {
        [$workspace, $pic, $system] = $this->system();

        // Six hours each and six hours a day: three requests, three consecutive days.
        $first = $this->approved($workspace, $system, $pic, 6 * 60, '2026-07-30 09:00:00');
        $second = $this->approved($workspace, $system, $pic, 6 * 60, '2026-07-31 09:00:00');
        $third = $this->approved($workspace, $system, $pic, 6 * 60, '2026-08-01 09:00:00');

        $this->assertSame(3, app(ScheduleApprovedRequests::class)->handle($workspace));

        $this->assertSame('2026-08-03', $first->fresh()->scheduled_start->format('Y-m-d'));
        $this->assertSame('2026-08-04', $second->fresh()->scheduled_start->format('Y-m-d'));
        $this->assertSame('2026-08-05', $third->fresh()->scheduled_start->format('Y-m-d'));

        foreach ([$first, $second, $third] as $request) {
            $this->assertSame(FeatureRequestStatus::SCHEDULED, $request->fresh()->status);
            $this->assertSame($pic->id, $request->fresh()->assignee_id);
        }
    }

    public function test_an_approval_without_an_estimate_is_left_in_the_queue(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $unestimated = $this->approved($workspace, $system, $pic, null, '2026-07-30 09:00:00');
        $estimated = $this->approved($workspace, $system, $pic, 6 * 60, '2026-07-31 09:00:00');

        $this->assertSame(1, app(ScheduleApprovedRequests::class)->handle($workspace));

        $this->assertSame(FeatureRequestStatus::APPROVED, $unestimated->fresh()->status);
        $this->assertNull($unestimated->fresh()->scheduled_start);
        $this->assertSame(FeatureRequestStatus::SCHEDULED, $estimated->fresh()->status);
    }

    public function test_work_beyond_the_horizon_keeps_its_place_instead_of_being_forced_in(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $workspace->update(['settings_json' => ['horizon_days' => 7]]);

        $oversized = $this->approved($workspace, $system, $pic, 500 * 60, '2026-07-30 09:00:00');
        $fits = $this->approved($workspace, $system, $pic, 6 * 60, '2026-07-31 09:00:00');

        $this->assertSame(1, app(ScheduleApprovedRequests::class)->handle($workspace));

        $this->assertSame(FeatureRequestStatus::APPROVED, $oversized->fresh()->status);
        $this->assertSame(1, app(ScheduleApprovedRequests::class)->queuePosition($oversized->fresh()));
        $this->assertNull(app(ScheduleApprovedRequests::class)->queuePosition($fits->fresh()));
    }

    public function test_a_second_drain_leaves_already_scheduled_work_alone(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $request = $this->approved($workspace, $system, $pic, 6 * 60, '2026-07-30 09:00:00');

        app(ScheduleApprovedRequests::class)->handle($workspace);
        $start = $request->fresh()->scheduled_start;

        $this->assertSame(0, app(ScheduleApprovedRequests::class)->handle($workspace));
        $this->assertEquals($start, $request->fresh()->scheduled_start);
    }

    public function test_the_scheduler_keeps_the_pic_chosen_during_approval(): void
    {
        [$workspace, $systemPic, $system] = $this->system();
        $chosenPic = $this->member($workspace, WorkspaceRole::MEMBER);
        $request = $this->approved($workspace, $system, $systemPic, 6 * 60, '2026-07-30 09:00:00');
        $request->forceFill(['assignee_id' => $chosenPic->id])->save();

        $this->assertSame(1, app(ScheduleApprovedRequests::class)->handle($workspace));
        $this->assertSame($chosenPic->id, $request->fresh()->assignee_id);
    }

    public function test_the_approval_flow_actually_records_an_estimate(): void
    {
        [$workspace, $pic, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        $request = FeatureRequest::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'requester_id' => $requester->id,
            'title' => 'Export the monthly stock report',
            'problem' => 'We copy the numbers into a spreadsheet by hand every month and it takes two days.',
            'desired_outcome' => 'A download button that produces the same columns we already use.',
            'benefit' => 'Saves about two staff days each month and reduces transcription errors.',
            'urgency' => RequestUrgency::NORMAL,
            'status' => FeatureRequestStatus::PENDING_REVIEW,
        ]);

        $this->actingAs($pic)
            ->post(route('app.approvals.decide', [$workspace, $request]), [
                'decision' => FeatureRequestStatus::APPROVED->value,
                'estimated_hours' => 7.5,
                'assignee_public_id' => $pic->public_id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(450, $request->fresh()->estimated_minutes);
        $this->assertSame($pic->id, $request->fresh()->assignee_id);
    }

    private function system(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Inventory Core', 'description' => null, 'color' => '#8b5cf6', 'pic_id' => $owner->id,
        ]);

        return [$workspace, $owner, $system];
    }

    private function member(Workspace $workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $user->id, 'role' => $role,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);

        return $user;
    }

    private function approved(Workspace $workspace, Project $system, User $reviewer, ?int $minutes, string $reviewedAt): FeatureRequest
    {
        return FeatureRequest::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'requester_id' => $this->member($workspace, WorkspaceRole::REQUESTER)->id,
            'title' => 'Approved at '.$reviewedAt,
            'problem' => 'The current process is manual and takes far longer than anyone budgeted for.',
            'desired_outcome' => 'The same output, produced by the system rather than by hand.',
            'benefit' => 'Saves about two staff days each month and reduces transcription errors.',
            'urgency' => RequestUrgency::NORMAL,
            'status' => FeatureRequestStatus::APPROVED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => $reviewedAt,
            'estimated_minutes' => $minutes,
        ]);
    }
}
