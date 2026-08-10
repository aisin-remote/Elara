<?php

namespace Tests\Feature\Support;

use App\Actions\Workspace\CreateWorkspace;
use App\Models\SupportArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpSupportFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_search_and_published_article_page_work(): void
    {
        [$user] = $this->workspace();
        $article = SupportArticle::create(['category' => 'Tasks', 'slug' => 'board-basics', 'title' => 'Board basics', 'body' => 'Move work across project statuses safely.', 'is_published' => true]);
        SupportArticle::create(['category' => 'Draft', 'slug' => 'secret-draft', 'title' => 'Secret draft', 'body' => 'Not ready for customers.', 'is_published' => false]);

        $this->actingAs($user)->get(route('help', ['q' => 'Board']))
            ->assertOk()->assertSee('Board basics')->assertDontSee('Secret draft');
        $this->actingAs($user)->get(route('help.articles.show', $article->slug))
            ->assertOk()->assertSee('Move work across project statuses safely.');
    }

    public function test_workspace_member_can_submit_support_ticket_and_outsider_is_hidden(): void
    {
        [$owner, $workspace] = $this->workspace();
        $payload = ['subject' => 'Calendar dates look wrong', 'body' => 'The date differs from our workspace timezone after saving an event.'];

        $this->actingAs($owner)->postJson(route('internal.support-tickets.store', $workspace), $payload)
            ->assertCreated()->assertJsonPath('data.status', 'open');
        $this->assertDatabaseHas('support_tickets', ['workspace_id' => $workspace->id, 'requester_id' => $owner->id, 'subject' => $payload['subject']]);

        $this->actingAs(User::factory()->create())->postJson(route('internal.support-tickets.store', $workspace), $payload)->assertNotFound();
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, ['name' => 'Orbitra Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1]);

        return [$owner, $workspace];
    }
}
