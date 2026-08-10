<?php

namespace Tests\Feature\Workspace;

use App\Actions\Project\CreateSystem;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RequestDeskAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** @return array<int, array<int, string>> */
    public static function deliveryRoles(): array
    {
        return [['owner'], ['admin'], ['manager'], ['supervisor'], ['member'], ['viewer']];
    }

    /**
     * @dataProvider deliveryRoles
     */
    public function test_the_delivery_team_is_refused_every_desk_route(string $role): void
    {
        [$workspace, , $system] = $this->workspace();
        $user = $role === 'owner'
            ? $workspace->owner
            : $this->member($workspace, WorkspaceRole::from($role));

        foreach ([
            route('desk.index'),
            route('desk.it-timeline'),
            route('desk.validations.index'),
            route('desk.requests.create', $workspace),
            route('desk.project-requests.create', $workspace),
        ] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }

        $this->actingAs($user)
            ->post(route('desk.requests.store', $workspace), $this->payload($system))
            ->assertForbidden();

        $this->assertSame(0, FeatureRequest::count());
    }

    public function test_a_requester_still_reaches_their_desk(): void
    {
        [$workspace, , $system] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        $this->actingAs($requester)->get(route('desk.index'))->assertOk();
        config()->set('organization.workspace_public_id', $workspace->public_id);
        $this->actingAs($requester)->get(route('desk.it-timeline'))->assertOk();
        $this->actingAs($requester)->get(route('desk.validations.index'))->assertOk();
        $this->actingAs($requester)
            ->post(route('desk.requests.store', $workspace), $this->payload($system))
            ->assertRedirect();

        $this->assertSame(1, FeatureRequest::count());
    }

    public function test_being_it_elsewhere_does_not_close_the_desk_you_are_a_requester_in(): void
    {
        [$requesterWorkspace, , $system] = $this->workspace();
        $person = $this->member($requesterWorkspace, WorkspaceRole::REQUESTER);

        // The same human joins another workspace's delivery team.
        [$itWorkspace] = $this->workspace();
        $itWorkspace->memberships()->create([
            'user_id' => $person->id, 'role' => WorkspaceRole::MEMBER,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);

        $this->actingAs($person)->get(route('desk.index'))->assertOk();
        $this->actingAs($person)
            ->post(route('desk.requests.store', $requesterWorkspace), $this->payload($system))
            ->assertRedirect();
    }

    public function test_a_requester_is_still_refused_the_delivery_desk(): void
    {
        [$workspace] = $this->workspace();
        $requester = $this->member($workspace, WorkspaceRole::REQUESTER);

        // The mirror gate from Phase 8 is untouched by the new one.
        $this->actingAs($requester)->get(route('app.dashboard', $workspace))->assertForbidden();
    }

    /** @return array{0: Workspace, 1: User, 2: Project} */
    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, [
            'name' => 'Product Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1,
        ]);
        $system = app(CreateSystem::class)->handle($workspace, $owner, [
            'name' => 'Inventory Core', 'description' => null, 'color' => '#8b5cf6', 'pic_id' => $owner->id,
        ]);

        return [$workspace, $owner, $system];
    }

    private function member(Workspace $workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $user->id, 'role' => $role,
            'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);

        return $user;
    }

    private function payload(Project $system): array
    {
        return [
            'system_public_id' => $system->public_id,
            'title' => 'Export the monthly stock report',
            'problem' => 'We copy the numbers into a spreadsheet by hand every month and it takes two days.',
            'desired_outcome' => 'A download button that produces the same columns we already use.',
            'urgency' => 'normal',
        ];
    }
}
