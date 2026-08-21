<?php

namespace Tests\Feature\Discussion;

use App\Actions\Workspace\CreateWorkspace;
use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\RequestUrgency;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\DiscussionComment;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Notifications\OrbitraNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiscussionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_participants_can_comment_reply_mention_attach_pin_and_mark_read(): void
    {
        Notification::fake();
        Storage::fake('local');
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, ['name' => 'Delivery', 'timezone' => 'Asia/Jakarta', 'locale' => 'en', 'week_start' => 1]);
        $requester = User::factory()->create();
        $workspace->memberships()->create(['user_id' => $requester->id, 'role' => WorkspaceRole::REQUESTER, 'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now()]);
        $system = Project::factory()->create(['workspace_id' => $workspace->id, 'owner_id' => $owner->id, 'type' => ProjectType::SYSTEM, 'status' => ProjectStatus::ACTIVE]);
        $featureRequest = FeatureRequest::create([
            'workspace_id' => $workspace->id, 'project_id' => $system->id, 'requester_id' => $requester->id,
            'title' => 'Improve receiving', 'problem' => str_repeat('Problem ', 12), 'desired_outcome' => str_repeat('Outcome ', 12),
            'benefit' => str_repeat('Benefit ', 12), 'urgency' => RequestUrgency::NORMAL, 'status' => FeatureRequestStatus::PENDING_REVIEW,
        ]);

        $this->actingAs($requester)->post(route('internal.discussions.comments.store', ['feature-request', $featureRequest->public_id]), [
            'body' => '@'.$owner->name.' please confirm the scope.',
            'mentioned_user_public_ids' => [$owner->public_id],
            'attachments' => [UploadedFile::fake()->create('scope.pdf', 20, 'application/pdf')],
        ])->assertRedirect();

        $comment = DiscussionComment::firstOrFail();
        $file = ProjectFile::firstOrFail();
        $this->assertSame([$owner->id], $comment->mentions_json);
        $this->actingAs($requester)->get(route('internal.discussions.files.download', $file->public_id))->assertOk();
        Notification::assertSentTo($owner, OrbitraNotification::class);

        $this->actingAs($owner)->post(route('internal.discussions.comments.store', ['feature-request', $featureRequest->public_id]), [
            'body' => 'Scope confirmed.', 'parent_public_id' => $comment->public_id,
        ])->assertRedirect();
        $this->actingAs($owner)->patch(route('internal.discussions.comments.pin', $comment), ['pinned' => 1])->assertRedirect();
        $this->assertNotNull($comment->fresh()->pinned_at);
        $this->actingAs($owner)->post(route('internal.discussions.read', ['feature-request', $featureRequest->public_id]))->assertRedirect();
        $this->assertDatabaseHas('discussion_reads', ['user_id' => $owner->id, 'subject_id' => $featureRequest->id]);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->postJson(route('internal.discussions.comments.store', ['feature-request', $featureRequest->public_id]), ['body' => 'No access'])
            ->assertForbidden();
    }
}
