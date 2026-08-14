<?php

namespace Tests\Feature\Request;

use App\Actions\Project\CreateSystem;
use App\Actions\Request\TransitionFeatureRequest;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\FeatureRequestStatus;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\OrbitraNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FeatureRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_requester_submits_and_every_supervisor_is_notified(): void
    {
        Notification::fake();
        [$owner, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);
        $member = $this->member($workspace, WorkspaceRole::MEMBER);

        $this->actingAs($requester)->post(route('desk.requests.store', $workspace), $this->payload($system))
            ->assertRedirect();

        $request = FeatureRequest::firstOrFail();
        $this->assertSame(FeatureRequestStatus::PENDING_REVIEW, $request->status);
        $this->assertSame($system->id, $request->project_id);

        Notification::assertSentTo($supervisor, OrbitraNotification::class);
        Notification::assertSentTo($owner, OrbitraNotification::class);
        Notification::assertNotSentTo($member, OrbitraNotification::class);
        Notification::assertNothingSentTo($requester);
    }

    public function test_a_thin_request_is_refused_by_validation(): void
    {
        [, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        $this->actingAs($requester)
            ->post(route('desk.requests.store', $workspace), [
                ...$this->payload($system),
                'problem' => 'broken',
                'desired_outcome' => 'fix it',
                'benefit' => 'faster',
            ])
            ->assertSessionHasErrors(['problem', 'desired_outcome', 'benefit']);

        $this->assertSame(0, FeatureRequest::count());
    }

    public function test_a_system_without_a_pic_can_receive_requests(): void
    {
        [$owner, $workspace] = $this->workspace();
        $orphan = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Orphan', 'description' => null, 'color' => '#8b5cf6', 'pic_id' => $owner->id,
        ]);
        // Strip every manager, leaving the system without a PIC.
        $orphan->memberships()->delete();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        $this->actingAs($requester)
            ->get(route('desk.requests.create', $workspace))
            ->assertOk()
            ->assertSee('Orphan');
        $this->actingAs($requester)
            ->post(route('desk.requests.store', $workspace), $this->payload($orphan))
            ->assertRedirect();
        $this->assertSame($orphan->id, FeatureRequest::firstOrFail()->project_id);
    }

    public function test_supervisor_approves_and_the_requester_hears_about_it(): void
    {
        Notification::fake();
        [, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);
        $request = $this->submit($workspace, $system, $requester);

        $this->actingAs($supervisor)
            ->post(route('app.approvals.decide', [$workspace, $request]), ['decision' => 'approved', 'estimated_hours' => 8, 'assignee_public_id' => $supervisor->public_id])
            ->assertRedirect(route('app.approvals.index', $workspace));

        $request->refresh();
        $this->assertSame(FeatureRequestStatus::APPROVED, $request->status);
        $this->assertSame($supervisor->id, $request->reviewed_by);
        $this->assertSame($supervisor->id, $request->assignee_id);
        $this->assertNotNull($request->reviewed_at);
        Notification::assertSentTo($requester, OrbitraNotification::class);
        $this->assertDatabaseHas('activity_logs', ['action' => 'feature_request.approved']);
    }

    public function test_approval_requires_an_active_it_pic(): void
    {
        [, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);
        $viewer = $this->member($workspace, WorkspaceRole::VIEWER);
        $request = $this->submit($workspace, $system, $requester);

        $this->actingAs($supervisor)->post(route('app.approvals.decide', [$workspace, $request]), [
            'decision' => 'approved',
            'estimated_hours' => 8,
        ])->assertSessionHasErrors('assignee_public_id');

        $this->actingAs($supervisor)->post(route('app.approvals.decide', [$workspace, $request]), [
            'decision' => 'approved',
            'estimated_hours' => 8,
            'assignee_public_id' => $viewer->public_id,
        ])->assertSessionHasErrors('assignee_public_id');

        $this->assertSame(FeatureRequestStatus::PENDING_REVIEW, $request->fresh()->status);
    }

    public function test_rejecting_without_a_reason_is_refused(): void
    {
        [, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);
        $request = $this->submit($workspace, $system, $requester);

        $this->actingAs($supervisor)
            ->post(route('app.approvals.decide', [$workspace, $request]), ['decision' => 'rejected'])
            ->assertSessionHasErrors('decision_note');

        $this->assertSame(FeatureRequestStatus::PENDING_REVIEW, $request->fresh()->status);
    }

    public function test_needs_info_returns_the_ball_to_the_requester_and_back_again(): void
    {
        [, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);
        $request = $this->submit($workspace, $system, $requester);

        $this->actingAs($supervisor)->post(route('app.approvals.decide', [$workspace, $request]), [
            'decision' => 'needs_info', 'decision_note' => 'Which report exactly, and how often?',
        ])->assertRedirect();

        $request->refresh();
        $this->assertSame(FeatureRequestStatus::NEEDS_INFO, $request->status);
        $this->actingAs($requester)->get(route('desk.requests.show', $request))
            ->assertOk()->assertSee('Which report exactly');

        $this->actingAs($requester)->post(route('desk.requests.resubmit', $request), [
            'problem' => 'The monthly stock report is assembled by hand and takes two days.',
            'desired_outcome' => 'A download button that produces the same columns we use now.',
            'benefit' => 'Saves about two staff days each month and reduces transcription errors.',
        ])->assertRedirect();

        $this->assertSame(FeatureRequestStatus::PENDING_REVIEW, $request->fresh()->status);
    }

    public function test_a_manager_decides_a_feature_request_without_waiting_for_a_supervisor(): void
    {
        [, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $manager = $this->member($workspace, WorkspaceRole::MANAGER);
        $request = $this->submit($workspace, $system, $requester);

        $this->actingAs($manager)->get(route('app.approvals.index', $workspace))->assertOk();

        // One signature carries a feature request: supervisor or manager, whoever is available.
        // The two-step order stays on project requests only.
        $this->actingAs($manager)
            ->post(route('app.approvals.decide', [$workspace, $request]), [
                'decision' => 'approved',
                'estimated_hours' => 8,
                'assignee_public_id' => $manager->public_id,
            ])
            ->assertRedirect();

        $this->assertSame(FeatureRequestStatus::APPROVED, $request->fresh()->status);
    }

    public function test_the_approval_queue_uses_compact_sla_copy(): void
    {
        [, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $manager = $this->member($workspace, WorkspaceRole::MANAGER);
        $request = $this->submit($workspace, $system, $requester);
        $request->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->actingAs($manager)
            ->get(route('app.approvals.index', $workspace))
            ->assertOk()
            ->assertSee('Overdue')
            ->assertSee('ITD supervisor')
            ->assertDontSee('SLA breached by')
            ->assertDontSee('Owner: ITD supervisor');
    }

    public function test_an_approver_can_pin_the_dates_instead_of_leaving_them_to_the_planner(): void
    {
        [$owner, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $request = $this->submit($workspace, $system, $requester);

        $this->actingAs($owner)
            ->post(route('app.approvals.decide', [$workspace, $request]), [
                'decision' => 'approved',
                'estimated_hours' => 8,
                'assignee_public_id' => $owner->public_id,
                'scheduled_start' => '2026-09-01',
                'scheduled_due' => '2026-09-03',
            ])
            ->assertRedirect();

        $decided = $request->fresh();

        // Scheduled outright: the drain only touches requests that are still approved, so
        // typed dates survive the planner's next run.
        $this->assertSame(FeatureRequestStatus::SCHEDULED, $decided->status);
        $this->assertSame('2026-09-01', $decided->scheduled_start->toDateString());
        $this->assertSame('2026-09-03', $decided->scheduled_due->toDateString());

        // submit() authenticates as the requester, so the second one is raised up front.
        $second = $this->submit($workspace, $system, $requester);

        $this->actingAs($owner)
            ->post(route('app.approvals.decide', [$workspace, $second]), [
                'decision' => 'approved',
                'estimated_hours' => 8,
                'assignee_public_id' => $owner->public_id,
                'scheduled_due' => '2026-09-03',
            ])
            ->assertSessionHasErrors('scheduled_start');
    }

    public function test_a_requester_sees_only_their_own_requests(): void
    {
        [, $workspace, $system] = $this->system();
        $mine = $this->member($workspace, WorkspaceRole::REQUESTER);
        $theirs = $this->member($workspace, WorkspaceRole::REQUESTER);
        $myRequest = $this->submit($workspace, $system, $mine);
        $theirRequest = $this->submit($workspace, $system, $theirs);

        $this->actingAs($mine)->get(route('desk.index'))
            ->assertOk()
            ->assertSee($myRequest->title)
            ->assertDontSee($theirRequest->public_id);

        $this->actingAs($mine)->get(route('desk.requests.show', $theirRequest))->assertForbidden();
    }

    public function test_requester_history_is_collapsed_after_five_entries(): void
    {
        [, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $request = $this->submit($workspace, $system, $requester);

        foreach (range(1, 4) as $step) {
            ActivityLog::record($workspace, $request, 'feature_request.history_'.$step, $requester);
        }

        $this->actingAs($requester)->get(route('desk.requests.show', $request))
            ->assertOk()
            ->assertSee('History')
            ->assertSee('Show all 6 entries')
            ->assertDontSee('Perkembangan');
    }

    public function test_an_illegal_transition_is_refused_by_the_action(): void
    {
        [$owner, $workspace, $system] = $this->system();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $request = $this->submit($workspace, $system, $requester);

        app(TransitionFeatureRequest::class)->handle($request, FeatureRequestStatus::APPROVED, $owner);

        // Approved cannot go back to under-review; only the Action knows that, which is
        // exactly why the rule lives there and not in a controller.
        $this->expectException(ValidationException::class);
        app(TransitionFeatureRequest::class)->handle($request->fresh(), FeatureRequestStatus::PENDING_REVIEW, $owner);
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);

        return [$owner, $workspace];
    }

    private function system(): array
    {
        [$owner, $workspace] = $this->workspace();
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Inventory Core', 'description' => null, 'color' => '#8b5cf6', 'pic_id' => $owner->id,
        ]);

        return [$owner, $workspace, $system];
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

    private function payload(Project $system): array
    {
        return [
            'system_public_id' => $system->public_id,
            'title' => 'Export the monthly stock report',
            'problem' => 'We copy the numbers into a spreadsheet by hand every month and it takes two days.',
            'desired_outcome' => 'A download button that produces the same columns we already use.',
            'benefit' => 'Saves about two staff days each month and reduces transcription errors.',
            'urgency' => 'normal',
        ];
    }

    private function submit(Workspace $workspace, Project $system, User $requester): FeatureRequest
    {
        $this->actingAs($requester)->post(route('desk.requests.store', $workspace), [
            ...$this->payload($system),
            'title' => 'Request from '.$requester->first_name,
        ]);

        return FeatureRequest::where('requester_id', $requester->id)->latest('id')->firstOrFail();
    }
}
