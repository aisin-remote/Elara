<?php

namespace Tests\Feature\Messaging;

use App\Actions\Workspace\CreateWorkspace;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessagingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_members_can_create_and_reuse_a_direct_conversation(): void
    {
        [$owner, $workspace, $member] = $this->workspaceWithMember();
        $payload = ['type' => 'direct', 'participant_public_ids' => [$member->public_id]];

        $first = $this->actingAs($owner)->postJson(route('internal.conversations.store', $workspace), $payload)
            ->assertCreated()->json('data.public_id');
        $second = $this->actingAs($owner)->postJson(route('internal.conversations.store', $workspace), $payload)
            ->assertCreated()->json('data.public_id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Conversation::count());
        $this->assertSame(2, Conversation::firstOrFail()->participantRecords()->count());
    }

    public function test_participant_can_send_attachment_and_event_is_dispatched(): void
    {
        Storage::fake('local');
        Event::fake([MessageSent::class]);
        [$owner, $workspace, $member] = $this->workspaceWithMember();
        $conversation = $this->directConversation($owner, $workspace, $member);

        $response = $this->actingAs($owner)->post(route('internal.messages.store', $conversation), [
            'body' => 'Here is the brief.',
            'attachments' => [UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $message = Message::where('public_id', $response->json('data.public_id'))->firstOrFail();
        $file = $message->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        Event::assertDispatched(MessageSent::class, fn ($event) => $event->message->is($message));
    }

    public function test_non_participant_cannot_access_conversation_or_message_attachment(): void
    {
        Storage::fake('local');
        [$owner, $workspace, $member] = $this->workspaceWithMember();
        $outsider = $this->addMember($workspace);
        $conversation = $this->directConversation($owner, $workspace, $member);
        $response = $this->actingAs($owner)->post(route('internal.messages.store', $conversation), [
            'attachments' => [UploadedFile::fake()->create('private.pdf', 20, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated();
        $file = Message::where('public_id', $response->json('data.public_id'))->firstOrFail()->attachments()->firstOrFail();

        $this->actingAs($outsider)->getJson(route('internal.messages.index', $conversation))->assertNotFound();
        $this->actingAs($outsider)->get(route('internal.files.download', $file))->assertForbidden();
    }

    public function test_messages_use_cursor_pagination_and_participant_can_mark_read(): void
    {
        [$owner, $workspace, $member] = $this->workspaceWithMember();
        $conversation = $this->directConversation($owner, $workspace, $member);
        $messages = collect(range(1, 4))->map(fn ($number) => $conversation->messages()->create([
            'sender_id' => $owner->id, 'body' => 'Message '.$number,
        ]));

        $this->actingAs($member)->getJson(route('internal.messages.index', [$conversation, 'per_page' => 2]))
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.body', 'Message 4')
            ->assertJson(fn ($json) => $json->whereType('meta.next_cursor', 'string')->etc());
        $this->actingAs($member)->postJson(route('internal.conversations.read', $conversation), [
            'message_public_id' => $messages->last()->public_id,
        ])->assertOk();
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id, 'user_id' => $member->id, 'last_read_message_id' => $messages->last()->id,
        ]);
    }

    public function test_sender_can_edit_delete_and_react_within_the_window(): void
    {
        [$owner, $workspace, $member] = $this->workspaceWithMember();
        $conversation = $this->directConversation($owner, $workspace, $member);
        $message = $conversation->messages()->create(['sender_id' => $owner->id, 'body' => 'Original']);

        $this->actingAs($member)->patchJson(route('internal.messages.update', $message), ['body' => 'No'])->assertForbidden();
        $this->actingAs($owner)->patchJson(route('internal.messages.update', $message), ['body' => 'Updated'])->assertOk()->assertJsonPath('data.body', 'Updated');
        $this->actingAs($member)->postJson(route('internal.message-reactions.store', $message), ['emoji' => '👍'])->assertOk();
        $this->assertDatabaseHas('message_reactions', ['message_id' => $message->id, 'user_id' => $member->id, 'emoji' => '👍']);
        $this->actingAs($owner)->deleteJson(route('internal.messages.destroy', $message))->assertOk();
        $this->assertSoftDeleted('messages', ['id' => $message->id]);
    }

    public function test_viewer_cannot_send_and_messages_page_renders_real_controls(): void
    {
        [$owner, $workspace, $member] = $this->workspaceWithMember(WorkspaceRole::VIEWER);
        $conversation = $this->directConversation($owner, $workspace, $member);

        $this->actingAs($member)->postJson(route('internal.messages.store', $conversation), ['body' => 'Blocked'])->assertForbidden();
        $this->actingAs($owner)->get(route('app.messages.index', $workspace))
            ->assertOk()->assertSee('Conversations')->assertSee('Schedule call')->assertSee('Write a message');
    }

    private function workspaceWithMember(WorkspaceRole $role = WorkspaceRole::MEMBER): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Orbitra Studio', 'timezone' => 'Asia/Jakarta', 'locale' => 'en', 'week_start' => 1,
        ]);

        return [$owner, $workspace, $this->addMember($workspace, $role)];
    }

    private function addMember($workspace, WorkspaceRole $role = WorkspaceRole::MEMBER): User
    {
        $user = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $user->id, 'role' => $role, 'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);

        return $user;
    }

    private function directConversation(User $owner, $workspace, User $member): Conversation
    {
        $publicId = $this->actingAs($owner)->postJson(route('internal.conversations.store', $workspace), [
            'type' => 'direct', 'participant_public_ids' => [$member->public_id],
        ])->assertCreated()->json('data.public_id');

        return Conversation::where('public_id', $publicId)->firstOrFail();
    }
}
