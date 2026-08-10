<?php

namespace Tests\Feature\Request;

use App\Actions\Request\TransitionProjectRequest;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectRequestStatus;
use App\Enums\ProjectType;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\OrbitraNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_proposal_without_a_business_case_is_refused(): void
    {
        [, $workspace] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        $this->actingAs($requester)
            ->post(route('desk.project-requests.store', $workspace), [
                'title' => 'Supplier portal',
                'benefit' => 'It would be nice',
                'concept' => 'A portal',
                'business_process' => 'Procurement',
                'flow' => 'They log in',
            ])
            ->assertSessionHasErrors(['benefit', 'concept', 'business_process', 'flow']);

        $this->assertSame(0, ProjectRequest::count());
    }

    public function test_submission_lands_awaiting_a_meeting_and_supervisors_hear_about_it(): void
    {
        Notification::fake();
        [, $workspace] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);
        $manager = $this->member($workspace, WorkspaceRole::MANAGER);

        $this->actingAs($requester)
            ->post(route('desk.project-requests.store', $workspace), $this->payload())
            ->assertRedirect();

        $request = ProjectRequest::firstOrFail();
        $this->assertSame(ProjectRequestStatus::PENDING_MEETING, $request->status);

        Notification::assertSentTo($supervisor, OrbitraNotification::class);
        // The manager is the second signature; they are not pulled in at intake.
        Notification::assertNotSentTo($manager, OrbitraNotification::class);
    }

    public function test_nobody_can_sign_before_the_meeting_is_recorded(): void
    {
        [$owner, $workspace] = $this->workspace();
        $request = $this->submit($workspace);
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);

        // The gate is in the Action, so even a direct transition is refused.
        $this->expectException(ValidationException::class);
        app(TransitionProjectRequest::class)->handle($request, ProjectRequestStatus::PENDING_SPV, $supervisor);
    }

    public function test_the_meeting_gate_opens_the_first_signature(): void
    {
        [$owner, $workspace] = $this->workspace();
        $request = $this->submit($workspace);
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);

        $this->actingAs($supervisor)->post(route('app.approvals.projects.meeting', [$workspace, $request]), [
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $request->refresh();
        $this->assertNotNull($request->schedule_event_id);
        $this->assertSame(2, $request->meeting->attendees()->count(), 'Requester and supervisor are both invited.');
        $this->assertFalse($request->meetingHeld(), 'Booking is not holding.');

        $this->actingAs($supervisor)->post(route('app.approvals.projects.meeting-held', [$workspace, $request]), [
            'meeting_note' => 'Scope agreed; the ERP write-back is out of scope for phase one.',
        ])->assertRedirect();

        $this->assertSame(ProjectRequestStatus::PENDING_SPV, $request->fresh()->status);
    }

    public function test_two_signatures_create_the_project_and_the_requester_is_not_a_member(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);
        $manager = $this->member($workspace, WorkspaceRole::MANAGER);
        $request = $this->submit($workspace, $requester);
        $this->holdMeeting($workspace, $request, $supervisor);

        $this->actingAs($supervisor)
            ->post(route('app.approvals.projects.decide', [$workspace, $request]), ['decision' => 'approve'])
            ->assertRedirect();
        $this->assertSame(ProjectRequestStatus::PENDING_MANAGER, $request->fresh()->status);

        // The second signature carries the effort figure the planner needs.
        $this->actingAs($manager)
            ->post(route('app.approvals.projects.decide', [$workspace, $request]), ['decision' => 'approve', 'estimated_hours' => 40])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame(ProjectRequestStatus::APPROVED, $request->status);
        $this->assertSame(2400, $request->estimated_minutes);
        $this->assertSame($supervisor->id, $request->spv_id);
        $this->assertSame($manager->id, $request->manager_id);

        $project = Project::find($request->project_id);
        $this->assertNotNull($project);
        $this->assertSame(ProjectType::PROJECT, $project->type);
        $this->assertFalse(
            $project->memberships()->where('user_id', $requester->id)->exists(),
            'The requester follows progress from their own desk, not the project board.'
        );
        Notification::assertSentTo($requester, OrbitraNotification::class);
    }

    public function test_legacy_plan_configuration_cannot_block_project_creation(): void
    {
        [$owner, $workspace] = $this->workspace();
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);
        $manager = $this->member($workspace, WorkspaceRole::MANAGER);
        $request = $this->submit($workspace);
        $this->holdMeeting($workspace, $request, $supervisor);
        $this->actingAs($supervisor)->post(route('app.approvals.projects.decide', [$workspace, $request]), ['decision' => 'approve']);

        // Legacy package settings no longer affect delivery.
        config()->set('plans.plans.starter.limits.active_projects', 0);

        $this->actingAs($manager)
            ->post(route('app.approvals.projects.decide', [$workspace, $request]), ['decision' => 'approve', 'estimated_hours' => 40])
            ->assertRedirect();

        $this->assertNotNull($request->fresh()->project_id);
    }

    public function test_one_person_cannot_supply_both_signatures(): void
    {
        [$owner, $workspace] = $this->workspace();
        $request = $this->submit($workspace);
        // Owner can act at both stages, which is exactly the loophole being closed.
        $this->holdMeeting($workspace, $request, $owner);

        $this->actingAs($owner)
            ->post(route('app.approvals.projects.decide', [$workspace, $request]), ['decision' => 'approve'])
            ->assertRedirect();
        $this->assertSame(ProjectRequestStatus::PENDING_MANAGER, $request->fresh()->status);

        $this->actingAs($owner)
            ->post(route('app.approvals.projects.decide', [$workspace, $request]), ['decision' => 'approve'])
            ->assertForbidden();

        $this->assertSame(ProjectRequestStatus::PENDING_MANAGER, $request->fresh()->status);
        $this->assertNull($request->fresh()->project_id, 'No project is created without a real second signature.');
    }

    public function test_approving_twice_does_not_create_two_projects(): void
    {
        [$owner, $workspace] = $this->workspace();
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);
        $manager = $this->member($workspace, WorkspaceRole::MANAGER);
        $request = $this->submit($workspace);
        $this->holdMeeting($workspace, $request, $supervisor);

        $this->actingAs($supervisor)->post(route('app.approvals.projects.decide', [$workspace, $request]), ['decision' => 'approve']);
        $this->actingAs($manager)->post(route('app.approvals.projects.decide', [$workspace, $request]), ['decision' => 'approve', 'estimated_hours' => 40]);

        // A double click, a retry, a stale tab: the state machine refuses the second one.
        $this->actingAs($manager)
            ->post(route('app.approvals.projects.decide', [$workspace, $request]), ['decision' => 'approve'])
            ->assertForbidden();

        $this->assertSame(1, Project::where('name', 'Supplier self-service portal')->count());
    }

    public function test_rejecting_without_a_reason_is_refused(): void
    {
        [, $workspace] = $this->workspace();
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);
        $request = $this->submit($workspace);
        $this->holdMeeting($workspace, $request, $supervisor);

        $this->actingAs($supervisor)
            ->post(route('app.approvals.projects.decide', [$workspace, $request]), ['decision' => 'reject'])
            ->assertSessionHasErrors('note');

        $this->assertSame(ProjectRequestStatus::PENDING_SPV, $request->fresh()->status);
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);

        return [$owner, $workspace];
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

    private function payload(): array
    {
        return [
            'title' => 'Supplier self-service portal',
            'benefit' => 'Suppliers phone us for delivery dates, which costs the team about fifteen hours a week.',
            'concept' => 'A website suppliers sign into to see open purchase orders and confirm delivery dates.',
            'business_process' => 'Today a supplier calls procurement, procurement checks the ERP, then emails a confirmation.',
            'flow' => 'Supplier signs in, sees open orders, confirms or proposes a date, procurement is notified.',
        ];
    }

    private function submit(Workspace $workspace, ?User $requester = null): ProjectRequest
    {
        $requester ??= $this->member($workspace, WorkspaceRole::REQUESTER);
        $this->actingAs($requester)->post(route('desk.project-requests.store', $workspace), $this->payload());

        return ProjectRequest::where('requester_id', $requester->id)->latest('id')->firstOrFail();
    }

    private function holdMeeting(Workspace $workspace, ProjectRequest $request, User $supervisor): void
    {
        $this->actingAs($supervisor)->post(route('app.approvals.projects.meeting', [$workspace, $request]), [
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($supervisor)->post(route('app.approvals.projects.meeting-held', [$workspace, $request]), [
            'meeting_note' => 'Scope agreed; the ERP write-back is out of scope for phase one.',
        ]);

        $request->refresh();
    }
}
