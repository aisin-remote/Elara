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
                ->assertSee('Cara kerjanya, dari sini sampai selesai')
                ->assertSee('Satu tenggat yang jadi tanggung jawab Anda')
                // The menu name stays English on purpose, so the guide points at what is on screen.
                ->assertSee('Waiting on me')
                ->assertSee('Cara menulis permintaan yang cepat disetujui');
        }
    }

    public function test_the_project_form_names_the_meeting_and_both_signatures(): void
    {
        [$workspace, $requester] = $this->workspace();

        $this->actingAs($requester)
            ->get(route('desk.project-requests.create', $workspace))
            ->assertOk()
            ->assertSee('Rapat pembahasan')
            ->assertSee('Tanda tangan pertama')
            ->assertSee('Tanda tangan kedua')
            ->assertSee('harus orang yang berbeda', false);
    }

    public function test_the_validation_window_shown_is_the_workspace_setting_not_a_hard_coded_seven(): void
    {
        [$workspace, $requester] = $this->workspace();
        $workspace->update(['settings_json' => ['validation_window_days' => 4]]);

        $this->actingAs($requester)
            ->get(route('desk.requests.create', $workspace))
            ->assertOk()
            ->assertSee('4 hari')
            ->assertDontSee('7 hari');
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
