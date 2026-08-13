<?php

namespace Tests\Feature\Request;

use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\FeatureRequestStatus;
use App\Enums\RequestUrgency;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequestAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
    }

    public function test_a_requester_attaches_a_screenshot_to_their_own_request(): void
    {
        [$workspace, , $system] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $request = $this->request($workspace, $system, $requester);

        $this->actingAs($requester)
            ->post(route('internal.requests.attachments.store', $request), [
                'file' => UploadedFile::fake()->image('broken-report.png'),
            ])
            ->assertRedirect();

        $file = ProjectFile::firstOrFail();
        $this->assertSame($request->id, $file->attachable_id);
        $this->assertSame($request->getMorphClass(), $file->attachable_type);
        // Deliberately not filed into the target system's library.
        $this->assertNull($file->project_id);
        $this->assertSame(1, $request->attachments()->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'request.attachment_added']);
    }

    public function test_another_requester_can_neither_attach_nor_read_it(): void
    {
        [$workspace, , $system] = $this->workspace();
        $mine = $this->member($workspace, WorkspaceRole::REQUESTER);
        $theirs = $this->member($workspace, WorkspaceRole::REQUESTER);
        $request = $this->request($workspace, $system, $theirs);

        $this->actingAs($mine)
            ->post(route('internal.requests.attachments.store', $request), [
                'file' => UploadedFile::fake()->image('nope.png'),
            ])
            ->assertForbidden();

        // And a file already attached stays unreadable: without the policy branch this falls
        // through to WorkspacePolicy::view and every member could read it.
        $this->actingAs($theirs)->post(route('internal.requests.attachments.store', $request), [
            'file' => UploadedFile::fake()->image('theirs.png'),
        ]);

        $file = ProjectFile::firstOrFail();
        $this->actingAs($mine)->get(route('internal.files.download', $file))->assertForbidden();
        $this->actingAs($theirs)->get(route('internal.files.download', $file))->assertOk();
    }

    public function test_the_delivery_team_can_read_the_evidence_they_are_deciding_on(): void
    {
        [$workspace, $owner, $system] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $supervisor = $this->member($workspace, WorkspaceRole::SUPERVISOR);
        $request = $this->request($workspace, $system, $requester);

        $this->actingAs($requester)->post(route('internal.requests.attachments.store', $request), [
            'file' => UploadedFile::fake()->image('evidence.png'),
        ]);

        $file = ProjectFile::firstOrFail();
        $this->actingAs($supervisor)->get(route('internal.files.download', $file))->assertOk();
        $this->actingAs($owner)->get(route('internal.files.download', $file))->assertOk();
    }

    public function test_attaching_is_refused_once_the_request_is_closed(): void
    {
        [$workspace, , $system] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $request = $this->request($workspace, $system, $requester);
        $request->forceFill(['status' => FeatureRequestStatus::REJECTED])->save();

        $this->actingAs($requester)
            ->post(route('internal.requests.attachments.store', $request->fresh()), [
                'file' => UploadedFile::fake()->image('too-late.png'),
            ])
            ->assertForbidden();

        $this->assertSame(0, ProjectFile::count());
    }

    public function test_evidence_cannot_be_removed_after_a_decision_was_made_on_it(): void
    {
        [$workspace, , $system] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $request = $this->request($workspace, $system, $requester);

        $this->actingAs($requester)->post(route('internal.requests.attachments.store', $request), [
            'file' => UploadedFile::fake()->image('evidence.png'),
        ]);
        $file = ProjectFile::firstOrFail();

        // While open the uploader may take it back.
        $this->actingAs($requester)->delete(route('internal.request-attachments.destroy', $file))->assertRedirect();
        $this->assertSame(0, ProjectFile::count());

        // After a decision, it stays.
        $this->actingAs($requester)->post(route('internal.requests.attachments.store', $request), [
            'file' => UploadedFile::fake()->image('second.png'),
        ]);
        $second = ProjectFile::firstOrFail();
        $request->forceFill(['status' => FeatureRequestStatus::REJECTED])->save();

        $this->actingAs($requester)->delete(route('internal.request-attachments.destroy', $second))->assertForbidden();
        $this->assertSame(1, ProjectFile::count());
    }

    public function test_an_executable_is_refused(): void
    {
        [$workspace, , $system] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);
        $request = $this->request($workspace, $system, $requester);

        $this->actingAs($requester)
            ->post(route('internal.requests.attachments.store', $request), [
                'file' => UploadedFile::fake()->create('payload.exe', 12, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, ProjectFile::count());
    }

    /** @return array{0: Workspace, 1: User, 2: Project} */
    private function workspace(): array
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

    private function request(Workspace $workspace, Project $system, User $requester): FeatureRequest
    {
        return FeatureRequest::create([
            'workspace_id' => $workspace->id,
            'project_id' => $system->id,
            'requester_id' => $requester->id,
            'title' => 'Export the monthly stock report',
            'problem' => 'We copy the numbers by hand every month and it takes two days.',
            'desired_outcome' => 'A download button producing the columns finance already uses.',
            'benefit' => 'Saves about two staff days each month and reduces transcription errors.',
            'urgency' => RequestUrgency::NORMAL,
            'status' => FeatureRequestStatus::PENDING_REVIEW,
        ]);
    }
}
