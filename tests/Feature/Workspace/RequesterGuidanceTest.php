<?php

namespace Tests\Feature\Workspace;

use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequesterGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_forms_explain_the_journey_and_the_deadline_that_is_theirs(): void
    {
        [$workspace, $requester] = $this->workspace();

        foreach ([
            route('desk.requests.create', $workspace),
            route('desk.project-requests.create', $workspace),
        ] as $url) {
            $this->actingAs($requester)->get($url)->assertOk()
                ->assertSee('How it works, from request to delivery')
                ->assertSee('One deadline belongs to you')
                ->assertSee('Waiting on me')
                ->assertSee('How to write a request that is easy to assess');
        }
    }

    public function test_the_project_form_names_the_meeting_and_both_signatures(): void
    {
        [$workspace, $requester] = $this->workspace();

        $this->actingAs($requester)
            ->get(route('desk.project-requests.create', $workspace))
            ->assertOk()
            ->assertSee('Scoping meeting')
            ->assertSee('First ITD signature')
            ->assertSee('Second ITD signature')
            ->assertSee('Add objective')
            ->assertSee('Add benefit')
            ->assertSee('Add cost item')
            ->assertDontSee('objective-1')
            ->assertDontSee('benefit-1')
            ->assertDontSee('cost-item-1')
            ->assertSee('same person cannot supply both signatures', false);
    }

    public function test_the_feature_request_uses_the_same_searchable_selector_as_master_system(): void
    {
        [$workspace, $requester] = $this->workspace();

        $this->actingAs($requester)
            ->get(route('desk.requests.create', $workspace))
            ->assertOk()
            ->assertSee('Search systems…')
            ->assertSee('aria-haspopup="listbox"', false);
    }

    public function test_request_forms_do_not_repeat_the_approval_path_banner(): void
    {
        [$workspace, $requester] = $this->workspace();

        foreach ([
            route('desk.requests.create', $workspace),
            route('desk.project-requests.create', $workspace),
            route('desk.supporting.create', $workspace),
        ] as $url) {
            $this->actingAs($requester)
                ->get($url)
                ->assertOk()
                ->assertDontSee('Your approval path')
                ->assertDontSee('Organization profile not connected')
                ->assertDontSee('Sent directly to ITD');
        }
    }

    public function test_the_validation_window_shown_is_the_workspace_setting_not_a_hard_coded_seven(): void
    {
        [$workspace, $requester] = $this->workspace();
        $workspace->update(['settings_json' => ['validation_window_days' => 4]]);

        $this->actingAs($requester)
            ->get(route('desk.requests.create', $workspace))
            ->assertOk()
            ->assertSee('4 days')
            ->assertDontSee('7 days');
    }

    /** @return array{0: Workspace, 1: User} */
    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Inventory Core', 'description' => null, 'color' => '#8b5cf6', 'pic_id' => $owner->id,
        ]);

        $requester = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $requester->id, 'role' => WorkspaceRole::REQUESTER,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);

        return [$workspace, $requester];
    }
}
