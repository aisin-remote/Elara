<?php

namespace Tests\Feature\Ai;

use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectType;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\AiConversation;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AskAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.openai.key', 'test-key-not-a-real-secret');
        config()->set('services.openai.model', 'gpt-4o');
    }

    public function test_the_screen_is_available_but_chat_history_is_private_to_its_owner(): void
    {
        [$workspace, $owner] = $this->workspace();
        $conversation = AiConversation::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'title' => 'Release readiness',
            'model' => 'gpt-4o',
        ]);
        $conversation->messages()->create(['role' => 'user', 'body' => 'Are we ready?']);
        $conversation->messages()->create([
            'role' => 'assistant',
            'body' => '**Release is ready.** [Open task](http://localhost/app/tasks/example) <script>alert(1)</script>',
            'model' => 'gpt-4o',
        ]);

        $this->actingAs($owner)
            ->get(route('app.ai.show', [$workspace, $conversation]))
            ->assertOk()
            ->assertSee('Ask AI')
            ->assertSee('Release readiness')
            ->assertSee('Are we ready?')
            ->assertSee('<strong>Release is ready.</strong>', false)
            ->assertSee('>Open task</a>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('**Release is ready.**');

        $member = $this->member($workspace, WorkspaceRole::MEMBER);
        $this->actingAs($member)
            ->get(route('app.ai.show', [$workspace, $conversation]))
            ->assertNotFound();
    }

    public function test_requester_only_accounts_cannot_call_ask_ai(): void
    {
        [$workspace] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        $this->actingAs($requester)
            ->postJson(route('internal.ai.messages.store', $workspace), ['message' => 'Show my tasks.'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_a_streamed_answer_persists_history_model_and_usage_without_provider_storage(): void
    {
        [$workspace, $owner] = $this->workspace();
        Http::fake(['*/responses' => Http::response($this->textStream('Two tasks need attention.'), 200, [
            'Content-Type' => 'text/event-stream',
        ])]);

        $response = $this->actingAs($owner)
            ->postJson(route('internal.ai.messages.store', $workspace), [
                'message' => 'What needs attention?',
            ]);

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', (string) $response->headers->get('content-type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('event: conversation', $content);
        $this->assertStringContainsString('event: delta', $content);
        $this->assertStringContainsString('Two tasks need attention.', $content);

        $conversation = AiConversation::firstOrFail();
        $this->assertSame($owner->id, $conversation->user_id);
        $this->assertSame(['user', 'assistant'], $conversation->messages()->pluck('role')->all());
        $assistant = $conversation->messages()->where('role', 'assistant')->firstOrFail();
        $this->assertSame('gpt-4o', $assistant->model);
        $this->assertSame(120, $assistant->input_tokens);
        $this->assertSame(18, $assistant->output_tokens);
        $this->assertFalse($assistant->metadata_json['stored_by_provider']);

        Http::assertSent(function ($request) use ($owner): bool {
            $this->assertTrue($request['stream']);
            $this->assertFalse($request['store']);
            $this->assertNotSame($owner->public_id, $request['safety_identifier']);
            $this->assertSame('function', $request['tools'][0]['type']);

            return true;
        });
    }

    public function test_read_only_tools_return_visible_project_data_and_hide_other_projects(): void
    {
        [$workspace, $owner] = $this->workspace();
        $member = $this->member($workspace, WorkspaceRole::MEMBER);
        $visible = $this->project($workspace, $owner, 'Visible project');
        $hidden = $this->project($workspace, $owner, 'Hidden project');
        $visible->members()->attach($member->id, ['role' => 'member']);
        $this->task($visible, $owner, 'Secret visible migration');
        $this->task($hidden, $owner, 'Secret hidden payroll');

        Http::fakeSequence()
            ->push($this->toolStream('search_workspace', [
                'query' => 'Secret',
                'limit' => 10,
            ]), 200, ['Content-Type' => 'text/event-stream'])
            ->push($this->textStream('I found one visible task.'), 200, ['Content-Type' => 'text/event-stream']);

        $response = $this->actingAs($member)
            ->postJson(route('internal.ai.messages.store', $workspace), ['message' => 'Find secret work.']);

        $response->assertOk();
        $this->assertStringContainsString('I found one visible task.', $response->streamedContent());

        $requests = Http::recorded();
        $this->assertCount(2, $requests);
        $secondInput = json_encode($requests[1][0]->data()['input'], JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Secret visible migration', $secondInput);
        $this->assertStringNotContainsString('Secret hidden payroll', $secondInput);
        $assistant = AiConversation::firstOrFail()->messages()->where('role', 'assistant')->firstOrFail();
        $this->assertSame(200, $assistant->input_tokens);
        $this->assertSame(28, $assistant->output_tokens);
    }

    public function test_a_conversation_cannot_be_reused_by_another_user(): void
    {
        [$workspace, $owner] = $this->workspace();
        $conversation = AiConversation::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'title' => 'Private chat',
            'model' => 'gpt-4o',
        ]);
        $member = $this->member($workspace, WorkspaceRole::MEMBER);

        $this->actingAs($member)->postJson(route('internal.ai.messages.store', $workspace), [
            'message' => 'Continue this.',
            'conversation_public_id' => $conversation->public_id,
        ])->assertNotFound();

        Http::assertNothingSent();
    }

    private function textStream(string $text): string
    {
        $response = [
            'id' => 'resp_text_1',
            'model' => 'gpt-4o',
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => $text]],
            ]],
            'usage' => ['input_tokens' => 120, 'output_tokens' => 18],
        ];

        return $this->event('response.output_text.delta', ['type' => 'response.output_text.delta', 'delta' => $text])
            .$this->event('response.completed', ['type' => 'response.completed', 'response' => $response]);
    }

    private function toolStream(string $name, array $arguments): string
    {
        return $this->event('response.completed', [
            'type' => 'response.completed',
            'response' => [
                'id' => 'resp_tool_1',
                'model' => 'gpt-4o',
                'output' => [[
                    'type' => 'function_call',
                    'call_id' => 'call_1',
                    'name' => $name,
                    'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR),
                ]],
                'usage' => ['input_tokens' => 80, 'output_tokens' => 10],
            ],
        ]);
    }

    private function event(string $event, array $data): string
    {
        return 'event: '.$event."\n".'data: '.json_encode($data, JSON_THROW_ON_ERROR)."\n\n";
    }

    /** @return array{Workspace, User} */
    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);

        return [$workspace, $owner];
    }

    private function member(Workspace $workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $user->id,
            'role' => $role,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function project(Workspace $workspace, User $owner, string $name): Project
    {
        return Project::factory()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
            'name' => $name,
            'type' => ProjectType::PROJECT,
        ]);
    }

    private function task(Project $project, User $creator, string $title): Task
    {
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);

        return Task::factory()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'status_id' => $status->id,
            'creator_id' => $creator->id,
            'title' => $title,
        ]);
    }
}
