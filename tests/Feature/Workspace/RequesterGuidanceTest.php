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

    public function test_request_forms_offer_their_process_guide_as_a_pdf_download(): void
    {
        [$workspace, $requester] = $this->workspace();

        foreach ([
            [route('desk.requests.create', $workspace), 'elara-feature-request-guide.pdf'],
            [route('desk.project-requests.create', $workspace), 'elara-project-request-guide.pdf'],
            [route('desk.supporting.create', $workspace), 'elara-supporting-request-guide.pdf'],
        ] as [$url, $filename]) {
            $this->actingAs($requester)->get($url)->assertOk()
                ->assertSee('Download Guide')
                ->assertSee("docs/{$filename}", false)
                ->assertSee('download', false)
                ->assertDontSee('How it works, from request to delivery');

            $path = public_path("docs/{$filename}");
            $this->assertFileExists($path);
            $this->assertGreaterThan(1_000, filesize($path));
        }

        foreach ([
            [route('desk.requests.create', $workspace), 'elara-feature-request-guide-id.pdf'],
            [route('desk.project-requests.create', $workspace), 'elara-project-request-guide-id.pdf'],
            [route('desk.supporting.create', $workspace), 'elara-supporting-request-guide-id.pdf'],
        ] as [$url, $filename]) {
            $this->actingAs($requester)->get($url)->assertOk()
                ->assertSee('Unduh Panduan')
                ->assertSee("docs/{$filename}", false);

            $path = public_path("docs/{$filename}");
            $this->assertFileExists($path);
            $this->assertGreaterThan(1_000, filesize($path));
        }
    }

    public function test_the_project_form_keeps_its_dynamic_business_case_fields(): void
    {
        [$workspace, $requester] = $this->workspace();

        $this->actingAs($requester)
            ->get(route('desk.project-requests.create', $workspace))
            ->assertOk()
            ->assertSee('Add objective')
            ->assertSee('Add benefit')
            ->assertSee('Add cost item')
            ->assertDontSee('objective-1')
            ->assertDontSee('benefit-1')
            ->assertDontSee('cost-item-1');
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
