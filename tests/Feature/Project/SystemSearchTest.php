<?php

namespace Tests\Feature\Project;

use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_name_and_description(): void
    {
        [$workspace, $owner] = $this->catalogue();

        $this->actingAs($owner)
            ->get(route('app.features.index', ['workspace' => $workspace, 'search' => 'inventory']))
            ->assertOk()
            ->assertSee('Inventory Core')
            ->assertDontSee('Payroll Portal');

        // The description is searched too, so people find a system by what it does.
        $this->actingAs($owner)
            ->get(route('app.features.index', ['workspace' => $workspace, 'search' => 'payslips']))
            ->assertOk()
            ->assertSee('Payroll Portal')
            ->assertDontSee('Inventory Core');
    }

    public function test_filtering_by_pic_returns_only_the_systems_that_person_owns(): void
    {
        [$workspace, $owner, $pics] = $this->catalogue();

        $this->actingAs($owner)
            ->get(route('app.features.index', ['workspace' => $workspace, 'pic' => $pics['payroll']->public_id]))
            ->assertOk()
            ->assertSee('Payroll Portal')
            ->assertDontSee('Inventory Core');
    }

    public function test_a_search_with_no_match_says_so_rather_than_looking_empty(): void
    {
        [$workspace, $owner] = $this->catalogue();

        $this->actingAs($owner)
            ->get(route('app.features.index', ['workspace' => $workspace, 'search' => 'zzzz']))
            ->assertOk()
            ->assertSee('No systems match that')
            ->assertSee('Clear the search or the PIC filter');
    }

    public function test_the_picker_lists_only_people_who_actually_own_a_system(): void
    {
        [$workspace, $owner] = $this->catalogue();
        $bystander = $this->member($workspace, WorkspaceRole::MEMBER, 'Bystander');

        $this->actingAs($owner)
            ->get(route('app.features.index', $workspace))
            ->assertOk()
            // A picker offering someone who owns nothing returns an empty page and reads as broken.
            ->assertDontSee('<option value="'.$bystander->public_id.'"', false);
    }

    /** @return array{0: Workspace, 1: User, 2: array<string, User>} */
    private function catalogue(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);

        $payrollPic = $this->member($workspace, WorkspaceRole::MEMBER, 'Payroll');

        app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Inventory Core', 'description' => 'Stock levels and receiving.',
            'color' => '#8b5cf6', 'pic_id' => $owner->id,
        ]);
        app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Payroll Portal', 'description' => 'Monthly runs and payslips.',
            'color' => '#2eb0fb', 'pic_id' => $payrollPic->id,
        ]);

        return [$workspace, $owner, ['payroll' => $payrollPic]];
    }

    private function member(Workspace $workspace, WorkspaceRole $role, string $firstName): User
    {
        $user = User::factory()->create(['first_name' => $firstName]);
        $workspace->memberships()->create([
            'user_id' => $user->id, 'role' => $role,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);

        return $user;
    }
}
