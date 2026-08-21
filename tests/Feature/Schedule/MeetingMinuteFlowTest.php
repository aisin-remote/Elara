<?php

namespace Tests\Feature\Schedule;

use App\Actions\Project\CreateProject;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\MeetingMinutePublicationStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Feature;
use App\Models\MeetingMinute;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeetingMinuteFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributor_can_record_project_mom_with_tba_and_private_documents(): void
    {
        Storage::fake('local');
        [$owner, $workspace, $project, $feature] = $this->context();

        $response = $this->actingAs($owner)->post(route('internal.meeting-minutes.store', $workspace), [
            'title' => 'Delivery alignment',
            'meeting_at' => '2026-08-20 10:30:00',
            'summary' => 'The team agreed on the delivery scope.',
            'project_public_id' => $project->public_id,
            'items' => [
                $this->item('Prepare deployment plan', 'Alya'),
                $this->item('Confirm acceptance criteria', 'Bima', dueDate: '2026-08-28'),
                $this->item('Share the next meeting invite', 'Citra', status: 'done'),
            ],
            'attachments' => [UploadedFile::fake()->create('minutes.pdf', 120, 'application/pdf')],
        ]);
        $response->assertSessionHasNoErrors();

        $meetingMinute = MeetingMinute::query()->firstOrFail();
        $response->assertRedirect(route('app.schedule.minutes.show', [$workspace, $meetingMinute]));
        $this->assertSame($project->id, $meetingMinute->project_id);
        $this->assertDatabaseHas('meeting_minute_items', [
            'meeting_minute_id' => $meetingMinute->id,
            'content' => 'Prepare deployment plan',
            'project_id' => null,
            'due_date' => null,
        ]);

        $file = ProjectFile::query()->firstOrFail();
        $this->assertTrue($file->attachable->is($meetingMinute));
        Storage::disk('local')->assertExists($file->path);

        $this->actingAs($owner)->get(route('app.schedule.minutes.show', [$workspace, $meetingMinute]))
            ->assertOk()
            ->assertSee('Prepare deployment plan')
            ->assertSee('TBA')
            ->assertSee('minutes.pdf');
        $this->actingAs($owner)->get(route('app.schedule.minutes.index', $workspace))
            ->assertOk()
            ->assertSee('<table', false)
            ->assertSee('Project / system')
            ->assertSee('Action items')
            ->assertSee('Documents')
            ->assertSee($project->name);
        $this->actingAs($owner)->get(route('internal.files.download', $file))->assertOk();

        $this->actingAs($owner)->delete(route('internal.meeting-minutes.destroy', $meetingMinute))
            ->assertRedirect(route('app.schedule.minutes.index', $workspace));
        $this->assertSoftDeleted('meeting_minutes', ['id' => $meetingMinute->id]);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_minute_can_be_updated_and_replaces_action_items_atomically(): void
    {
        [$owner, $workspace, $project] = $this->context();
        $meetingMinute = $this->createMinute($workspace, $owner);

        $this->actingAs($owner)->patch(route('internal.meeting-minutes.update', $meetingMinute), [
            'title' => 'Updated alignment',
            'meeting_at' => '2026-08-21 09:00:00',
            'summary' => null,
            'project_public_id' => $project->public_id,
            'items' => [$this->item('Revised follow-up', 'Dina', status: 'in_progress')],
        ])->assertRedirect(route('app.schedule.minutes.show', [$workspace, $meetingMinute]));

        $this->assertSame(1, $meetingMinute->fresh()->items()->count());
        $this->assertDatabaseHas('meeting_minute_items', ['content' => 'Revised follow-up', 'status' => 'in_progress']);
        $this->assertDatabaseMissing('meeting_minute_items', ['content' => 'Original follow-up']);
    }

    public function test_mom_can_link_a_schedule_event_and_registered_pic(): void
    {
        [$owner, $workspace, $project, $feature] = $this->context();
        $event = ScheduleEvent::factory()->create([
            'workspace_id' => $workspace->id,
            'creator_id' => $owner->id,
            'title' => 'Scope review',
            'start_at' => '2026-08-20 03:00:00',
            'end_at' => '2026-08-20 04:00:00',
        ]);
        $event->attendees()->attach($owner->id);
        $deletedSystem = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
            'type' => ProjectType::SYSTEM,
            'status' => ProjectStatus::ACTIVE,
            'name' => 'Deleted system',
        ]);
        $orphanedFeature = Feature::create([
            'workspace_id' => $workspace->id,
            'project_id' => $deletedSystem->id,
            'name' => 'Orphaned feature',
            'description' => 'This should not be selectable.',
            'status' => 'active',
        ]);
        $deletedSystem->delete();

        $this->actingAs($owner)
            ->get(route('app.schedule.minutes.create', [$workspace, 'event' => $event->public_id]))
            ->assertOk()
            ->assertSee('Linked to Schedule')
            ->assertSee('Cubic Pro')
            ->assertDontSee($orphanedFeature->name)
            ->assertSee('x-teleport="body"', false)
            ->assertSee('min-w-[760px]', false)
            ->assertDontSee('Related to')
            ->assertSee('lg:grid-cols-[248px_minmax(0,1fr)]', false);

        $this->actingAs($owner)->post(route('internal.meeting-minutes.store', $workspace), [
            'title' => 'MOM – Scope review',
            'meeting_at' => '2026-08-20 10:00:00',
            'schedule_event_public_id' => $event->public_id,
            'project_public_id' => $project->public_id,
            'items' => [[
                ...$this->item('Confirm scope', $owner->name),
                'pic_user_public_id' => $owner->public_id,
            ]],
        ])->assertSessionHasNoErrors();

        $minute = MeetingMinute::firstOrFail();
        $this->assertSame($event->id, $minute->schedule_event_id);
        $this->assertSame($owner->id, $minute->items()->firstOrFail()->pic_user_id);

        $this->actingAs($owner)->getJson(route('internal.calendar.index', [
            'workspace' => $workspace,
            'start' => '2026-08-20',
            'end' => '2026-08-21',
        ]))->assertOk()->assertJsonFragment([
            'mom_label' => 'Open MOM',
            'mom_url' => route('app.schedule.minutes.show', [$workspace, $minute]),
        ]);
    }

    public function test_cross_workspace_project_is_rejected(): void
    {
        [$owner, $workspace] = $this->context();
        [, , $otherProject] = $this->context();

        $this->actingAs($owner)->postJson(route('internal.meeting-minutes.store', $workspace), [
            'title' => 'Invalid relation',
            'meeting_at' => '2026-08-20 10:30:00',
            'project_public_id' => $otherProject->public_id,
            'items' => [$this->item('Should fail', 'Alya')],
        ])->assertUnprocessable()->assertJsonValidationErrors(['project_public_id']);

        $this->assertDatabaseCount('meeting_minutes', 0);
    }

    public function test_viewer_cannot_mutate_and_outsider_cannot_bind_minutes_or_files(): void
    {
        Storage::fake('local');
        [$owner, $workspace] = $this->context();
        $viewer = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::VIEWER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        $meetingMinute = $this->createMinute($workspace, $owner);
        $file = ProjectFile::create([
            'workspace_id' => $workspace->id,
            'uploader_id' => $owner->id,
            'disk' => 'local',
            'path' => 'missing/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'attachable_type' => $meetingMinute->getMorphClass(),
            'attachable_id' => $meetingMinute->id,
        ]);

        $this->actingAs($viewer)->get(route('app.schedule.minutes.show', [$workspace, $meetingMinute]))->assertOk();
        $this->actingAs($viewer)->postJson(route('internal.meeting-minutes.store', $workspace), [])->assertForbidden();
        $this->actingAs($viewer)->deleteJson(route('internal.files.destroy', $file))->assertForbidden();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('app.schedule.minutes.show', [$workspace, $meetingMinute]))->assertNotFound();
        $this->actingAs($outsider)->get(route('internal.files.download', $file))->assertNotFound();
    }

    public function test_published_mom_tracks_revisions_and_can_be_locked_while_pic_updates_follow_up(): void
    {
        [$owner, $workspace] = $this->context();

        $this->actingAs($owner)->post(route('internal.meeting-minutes.store', $workspace), [
            'title' => 'Controlled MOM', 'meeting_at' => '2026-08-20 10:00:00',
            'publication_status' => 'published',
            'items' => [[...$this->item('Close the finding', $owner->name), 'pic_user_public_id' => $owner->public_id]],
        ])->assertSessionHasNoErrors();

        $minute = MeetingMinute::firstOrFail();
        $item = $minute->items()->firstOrFail();
        $this->assertSame(MeetingMinutePublicationStatus::PUBLISHED, $minute->publication_status);
        $this->assertDatabaseCount('meeting_minute_revisions', 1);

        $this->actingAs($owner)->patch(route('internal.meeting-minutes.publication', $minute), ['publication_status' => 'locked'])
            ->assertRedirect();
        $this->assertSame(MeetingMinutePublicationStatus::LOCKED, $minute->fresh()->publication_status);
        $this->assertDatabaseCount('meeting_minute_revisions', 2);

        $this->actingAs($owner)->patchJson(route('internal.meeting-minutes.update', $minute), [])->assertForbidden();
        $this->actingAs($owner)->patch(route('internal.meeting-minute-items.update', $item), ['status' => 'done'])->assertRedirect();
        $this->assertSame('done', $item->fresh()->status->value);
    }

    private function context(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => fake()->company(), 'timezone' => 'Asia/Jakarta', 'locale' => 'en', 'week_start' => 1,
        ]);
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Warehouse rollout', 'description' => null, 'color' => '#4f46e5',
            'status' => ProjectStatus::ACTIVE->value, 'start_date' => null, 'due_date' => null,
        ]);
        $system = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
            'type' => ProjectType::SYSTEM,
            'status' => ProjectStatus::ACTIVE,
            'name' => 'Cubic Pro',
        ]);
        $feature = Feature::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'name' => 'Receiving flow',
            'description' => 'Improve receiving.',
            'status' => 'active',
        ]);

        return [$owner, $workspace, $project, $feature];
    }

    private function createMinute($workspace, $owner): MeetingMinute
    {
        $minute = MeetingMinute::create([
            'workspace_id' => $workspace->id,
            'creator_id' => $owner->id,
            'title' => 'Original meeting',
            'meeting_at' => now(),
        ]);
        $minute->items()->create([
            'content' => 'Original follow-up',
            'pic_name' => 'Alya',
            'status' => 'outstanding',
            'position' => 1024,
        ]);

        return $minute;
    }

    private function item(string $content, string $pic, ?string $dueDate = null, string $status = 'outstanding'): array
    {
        return [
            'content' => $content,
            'pic_name' => $pic,
            'due_date' => $dueDate,
            'status' => $status,
        ];
    }
}
