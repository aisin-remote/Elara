<?php

namespace Tests\Feature\Ai;

use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DescriptionDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.openai.key', 'test-key-not-a-real-secret');
        config()->set('services.openai.model', 'gpt-4o');
    }

    public function test_an_it_member_can_expand_a_short_project_description(): void
    {
        $generated = 'The project will centralize printer support requests so office users can report incidents consistently. The scope covers request intake, ownership, progress visibility, and closure confirmation without inventing a new external integration. Success means the support team can track each request from submission through resolution.';
        Http::fake(['*/responses' => Http::response($this->openAiBody($generated))]);
        [$workspace, $owner] = $this->workspace();

        $this->actingAs($owner)
            ->postJson(route('internal.ai.descriptions.store', $workspace), [
                'kind' => 'project',
                'name' => 'Printer support portal',
                'description' => 'Bikin portal untuk laporan printer rusak.',
            ])
            ->assertOk()
            ->assertJsonPath('data.description', $generated);

        Http::assertSent(function ($request) {
            $body = $request->data();

            $this->assertFalse($body['store']);
            $this->assertSame('json_schema', $body['text']['format']['type']);
            $this->assertTrue($body['text']['format']['strict']);
            $this->assertStringContainsString('Printer support portal', $body['input'][0]['content']);
            $this->assertStringContainsString('laporan printer rusak', $body['input'][0]['content']);

            return true;
        });
    }

    public function test_a_feature_description_uses_the_selected_system_as_context(): void
    {
        $generated = 'The feature will let warehouse staff download a consistent stock report from Inventory Core. It covers the existing stock fields, a clear download action, and a verifiable file output while leaving unspecified formats and integrations as assumptions for review.';
        Http::fake(['*/responses' => Http::response($this->openAiBody($generated))]);
        [$workspace, $owner] = $this->workspace();
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Inventory Core',
            'description' => 'Stock levels and receiving.',
            'color' => '#8b5cf6',
            'pic_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->postJson(route('internal.ai.descriptions.store', $workspace), [
                'kind' => 'feature',
                'name' => 'Stock export',
                'description' => 'Kasih tombol download laporan stok.',
                'system_public_id' => $system->public_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.description', $generated);

        Http::assertSent(fn ($request) => str_contains($request->data()['input'][0]['content'], 'System: Inventory Core'));
    }

    public function test_an_it_member_can_generate_a_mom_summary_from_action_items(): void
    {
        $summary = 'Tim menyepakati peninjauan ruang lingkup. Alya menyiapkan dokumen teknis sebelum 28 Agustus 2026, sedangkan tindak lanjut tanpa tenggat tetap dicatat sebagai TBA.';
        Http::fake(['*/responses' => Http::response([
            'model' => 'gpt-4o',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode(['summary' => $summary], JSON_THROW_ON_ERROR),
                ]],
            ]],
        ])]);
        [$workspace, $owner] = $this->workspace();

        $this->actingAs($owner)
            ->postJson(route('internal.ai.meeting-summary', $workspace), [
                'title' => 'Scope alignment',
                'items' => [[
                    'content' => 'Prepare technical document',
                    'pic_name' => 'Alya',
                    'due_date' => '2026-08-28',
                    'status' => 'outstanding',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.summary', $summary);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $body['store'] === false
                && $body['text']['format']['name'] === 'meeting_summary'
                && str_contains($body['input'][0]['content'], 'Prepare technical document');
        });
    }

    public function test_the_create_forms_expose_the_ai_description_action(): void
    {
        [$workspace, $owner] = $this->workspace();

        $this->actingAs($owner)
            ->get(route('app.projects.create', $workspace))
            ->assertOk()
            ->assertSee('Generate with AI')
            ->assertSee('descriptionDraft(', false)
            ->assertSee('ai\\/descriptions', false);

        $this->actingAs($owner)
            ->get(route('app.features.create', $workspace))
            ->assertOk()
            ->assertSee('Generate with AI')
            ->assertSee('descriptionDraft(', false)
            ->assertSee('ai\\/descriptions', false);
    }

    public function test_a_viewer_cannot_generate_a_description(): void
    {
        Http::fake();
        [$workspace] = $this->workspace();
        $viewer = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::VIEWER,
            'status' => WorkspaceMemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->postJson(route('internal.ai.descriptions.store', $workspace), [
                'kind' => 'project',
                'name' => 'Restricted project',
                'description' => 'This request must not reach OpenAI.',
            ])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_an_openai_error_returns_a_safe_message_without_replacing_the_brief(): void
    {
        Http::fake(['*/responses' => Http::response(['error' => ['message' => 'Rate limit reached.']], 429)]);
        [$workspace, $owner] = $this->workspace();

        $this->actingAs($owner)
            ->postJson(route('internal.ai.descriptions.store', $workspace), [
                'kind' => 'project',
                'name' => 'Printer support portal',
                'description' => 'Bikin portal untuk laporan printer rusak.',
            ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'AI could not expand the description right now. Please try again.');
    }

    private function openAiBody(string $description): array
    {
        return [
            'model' => 'gpt-4o',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode(['description' => $description], JSON_THROW_ON_ERROR),
                ]],
            ]],
            'usage' => ['input_tokens' => 120, 'output_tokens' => 90],
        ];
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio',
            'timezone' => 'UTC',
            'locale' => 'en',
            'week_start' => 1,
        ]);

        return [$workspace, $owner];
    }
}
