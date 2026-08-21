<?php

namespace Tests\Feature\Operations;

use App\Actions\Workspace\CreateWorkspace;
use App\Enums\BreakdownStatus;
use App\Jobs\GenerateTaskBreakdown;
use App\Models\Project;
use App\Models\TaskBreakdown;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'organization.required' => false,
            'orbitra.system_health_email' => 'fabian@aiia.co.id',
        ]);
    }

    public function test_only_the_configured_developer_can_see_system_health(): void
    {
        [$developer, $workspace] = $this->workspace('fabian@aiia.co.id');
        [$other, $otherWorkspace] = $this->workspace('someone@example.com');

        $this->actingAs($developer)
            ->get(route('app.settings.profile', $workspace))
            ->assertOk()
            ->assertSee('System health');
        $this->actingAs($developer)
            ->get(route('app.settings.system-health', $workspace))
            ->assertOk()
            ->assertSee('Operational reliability')
            ->assertSee('Developer access');

        $this->actingAs($other)
            ->get(route('app.settings.profile', $otherWorkspace))
            ->assertOk()
            ->assertDontSee('System health');
        $this->actingAs($other)
            ->get(route('app.settings.system-health', $otherWorkspace))
            ->assertNotFound();
    }

    public function test_health_page_never_renders_the_openai_secret(): void
    {
        [$developer, $workspace] = $this->workspace('fabian@aiia.co.id');
        config(['services.openai.key' => 'do-not-render-this-secret']);

        $this->actingAs($developer)
            ->get(route('app.settings.system-health', $workspace))
            ->assertOk()
            ->assertSee('OpenAI credentials are configured.')
            ->assertDontSee('do-not-render-this-secret');
    }

    public function test_developer_can_retry_one_failed_ai_breakdown(): void
    {
        Bus::fake();
        [$developer, $workspace] = $this->workspace('fabian@aiia.co.id');
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $developer->id,
        ]);
        $breakdown = TaskBreakdown::create([
            'workspace_id' => $workspace->id,
            'subject_type' => $project->getMorphClass(),
            'subject_id' => $project->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'status' => BreakdownStatus::FAILED,
            'error_message' => 'Temporary provider failure.',
        ]);

        $this->actingAs($developer)
            ->post(route('app.settings.system-health.run', [$workspace, 'retry-breakdown']), [
                'target' => $breakdown->public_id,
            ])
            ->assertRedirect(route('app.settings.system-health', $workspace))
            ->assertSessionHas('status', 'The AI breakdown was queued again.');

        $this->assertSame(BreakdownStatus::PENDING, $breakdown->fresh()->status);
        $this->assertNull($breakdown->fresh()->error_message);
        Bus::assertDispatched(GenerateTaskBreakdown::class);
    }

    public function test_non_developer_cannot_post_an_operational_action(): void
    {
        [$user, $workspace] = $this->workspace('member@example.com');

        $this->actingAs($user)
            ->post(route('app.settings.system-health.run', [$workspace, 'integrity-check']))
            ->assertNotFound();
    }

    private function workspace(string $email): array
    {
        $owner = User::factory()->create(['email' => $email]);
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'ITD Workspace',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'en',
            'week_start' => 1,
        ]);

        return [$owner, $workspace];
    }
}
