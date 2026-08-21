<?php

namespace Tests\Feature\Schedule;

use App\Actions\Workspace\CreateWorkspace;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\MeetingMinute;
use App\Models\ProjectFile;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Notifications\OrbitraNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequesterScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_invite_it_members_and_both_desks_see_the_meeting(): void
    {
        Notification::fake();
        [$deliveryWorkspace, $itMember, $requesterWorkspace, $requester] = $this->workspaces();

        $this->actingAs($requester)
            ->get(route('desk.schedule.index', $requesterWorkspace))
            ->assertOk()
            ->assertSee('Meet with the IT team')
            ->assertSee($itMember->name)
            ->assertSee('Schedule');

        $this->actingAs($requester)
            ->post(route('desk.schedule.store', $requesterWorkspace), $this->payload([$itMember->public_id]))
            ->assertRedirect(route('desk.schedule.index', $requesterWorkspace))
            ->assertSessionHas('status', 'Meeting invitation sent to the IT team.');

        $event = ScheduleEvent::firstOrFail();
        $this->assertSame($deliveryWorkspace->id, $event->workspace_id);
        $this->assertSame($requester->id, $event->creator_id);
        $this->assertNull($event->project_id);
        $this->assertSame('2026-09-03 02:00:00', $event->start_at->utc()->format('Y-m-d H:i:s'));
        $this->assertEqualsCanonicalizing([$requester->id, $itMember->id], $event->attendees()->pluck('users.id')->all());

        Notification::assertSentTo($itMember, OrbitraNotification::class, function (OrbitraNotification $notification) use ($itMember, $event): bool {
            $data = $notification->toArray($itMember);

            return $data['event'] === 'team_activity'
                && $data['meta']['schedule_event_public_id'] === $event->public_id;
        });

        $this->actingAs($requester)
            ->getJson(route('desk.schedule.events', [
                'workspace' => $requesterWorkspace,
                'start' => '2026-09-01',
                'end' => '2026-09-08',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Requirements alignment');

        $this->actingAs($itMember)
            ->getJson(route('internal.calendar.index', [
                'workspace' => $deliveryWorkspace,
                'start' => '2026-09-01',
                'end' => '2026-09-08',
            ]))
            ->assertOk()
            ->assertJsonFragment(['type' => 'event', 'title' => 'Requirements alignment']);
    }

    public function test_requester_calendar_is_private_and_rejects_non_it_attendees(): void
    {
        [$deliveryWorkspace, $itMember, $requesterWorkspace, $requester] = $this->workspaces();
        $outsider = User::factory()->create();

        ScheduleEvent::factory()->create([
            'workspace_id' => $deliveryWorkspace->id,
            'creator_id' => $itMember->id,
            'title' => 'Private IT planning',
            'start_at' => '2026-09-03 02:00:00',
            'end_at' => '2026-09-03 03:00:00',
        ]);

        $this->actingAs($requester)
            ->getJson(route('desk.schedule.events', [
                'workspace' => $requesterWorkspace,
                'start' => '2026-09-01',
                'end' => '2026-09-08',
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($requester)
            ->post(route('desk.schedule.store', $requesterWorkspace), $this->payload([$outsider->public_id]))
            ->assertSessionHasErrors('attendee_public_ids.0');

        $this->assertDatabaseCount('schedule_events', 1);
    }

    public function test_requester_can_create_mom_with_registered_and_free_text_pics(): void
    {
        Storage::fake('local');
        config(['organization.required' => false]);
        [$deliveryWorkspace, $itMember, $requesterWorkspace, $requester] = $this->workspaces();
        $event = ScheduleEvent::factory()->create([
            'workspace_id' => $deliveryWorkspace->id,
            'creator_id' => $requester->id,
            'title' => 'Requester alignment',
            'start_at' => '2026-09-03 02:00:00',
            'end_at' => '2026-09-03 03:00:00',
        ]);
        $event->attendees()->attach([$requester->id, $itMember->id]);

        $this->actingAs($requester)
            ->get(route('desk.schedule.mom.create', [$requesterWorkspace, $event->public_id]))
            ->assertOk()
            ->assertSee('Action items')
            ->assertSee($itMember->name);

        $this->actingAs($requester)->post(route('desk.schedule.mom.store', [$requesterWorkspace, $event->public_id]), [
            'title' => 'MOM – Requester alignment',
            'meeting_at' => '2026-09-03 09:00:00',
            'schedule_event_public_id' => $event->public_id,
            'items' => [
                [
                    'content' => 'Prepare technical review',
                    'pic_name' => $itMember->name,
                    'pic_user_public_id' => $itMember->public_id,
                    'due_date' => null,
                    'status' => 'outstanding',
                ],
                [
                    'content' => 'Send vendor document',
                    'pic_name' => 'External Vendor',
                    'due_date' => null,
                    'status' => 'pending',
                ],
            ],
            'attachments' => [UploadedFile::fake()->create('notes.pdf', 64, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $minute = MeetingMinute::firstOrFail();
        $this->assertSame($event->id, $minute->schedule_event_id);
        $this->assertDatabaseHas('meeting_minute_items', ['pic_user_id' => $itMember->id, 'pic_name' => $itMember->name]);
        $this->assertDatabaseHas('meeting_minute_items', ['pic_user_id' => null, 'pic_name' => 'External Vendor']);
        $file = ProjectFile::firstOrFail();
        Storage::disk('local')->assertExists($file->path);

        $this->actingAs($requester)
            ->getJson(route('desk.schedule.events', [
                'workspace' => $requesterWorkspace,
                'start' => '2026-09-01',
                'end' => '2026-09-08',
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'mom_label' => 'Open MOM',
                'mom_url' => route('desk.schedule.mom.show', [$requesterWorkspace, $minute->public_id]),
            ]);

        $this->actingAs($requester)
            ->get(route('desk.schedule.mom.show', [$requesterWorkspace, $minute->public_id]))
            ->assertOk()
            ->assertSee('aria-label="View MOM version 1"', false)
            ->assertSee('Prepare technical review');

        $this->actingAs($requester)
            ->get(route('desk.schedule.mom.files.download', [$requesterWorkspace, $minute->public_id, $file->public_id]))
            ->assertOk();
    }

    private function workspaces(): array
    {
        $deliveryOwner = User::factory()->create();
        $deliveryWorkspace = app(CreateWorkspace::class)->handle($deliveryOwner, [
            'name' => "ITD's Workspace",
            'timezone' => 'Asia/Jakarta',
            'locale' => 'en',
            'week_start' => 1,
        ]);
        config(['organization.workspace_public_id' => $deliveryWorkspace->public_id]);

        $itMember = User::factory()->create(['first_name' => 'Rafie', 'last_name' => 'IT']);
        $deliveryWorkspace->memberships()->create([
            'user_id' => $itMember->id,
            'role' => WorkspaceRole::SUPERVISOR,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        $requesterWorkspaceOwner = User::factory()->create();
        $requesterWorkspace = app(CreateWorkspace::class)->handle($requesterWorkspaceOwner, [
            'name' => "QAU's Workspace",
            'timezone' => 'Asia/Jakarta',
            'locale' => 'en',
            'week_start' => 1,
        ]);
        $requester = User::factory()->create(['first_name' => 'QAU', 'last_name' => 'Requester']);
        $requesterWorkspace->memberships()->create([
            'user_id' => $requester->id,
            'role' => WorkspaceRole::REQUESTER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        return [$deliveryWorkspace, $itMember, $requesterWorkspace, $requester];
    }

    private function payload(array $attendees): array
    {
        return [
            'title' => 'Requirements alignment',
            'description' => 'Align the scope and next actions.',
            'start_at' => '2026-09-03 09:00:00',
            'end_at' => '2026-09-03 10:00:00',
            'meeting_url' => 'https://meet.example.test/alignment',
            'attendee_public_ids' => $attendees,
        ];
    }
}
