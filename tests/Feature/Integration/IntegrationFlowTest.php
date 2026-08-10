<?php

namespace Tests\Feature\Integration;

use App\Actions\Integration\ConnectIntegration;
use App\Actions\Project\CreateProject;
use App\Actions\Task\CreateTask;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\IntegrationProvider;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Models\IntegrationConnection;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class IntegrationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_state_is_required_and_connected_tokens_are_encrypted(): void
    {
        [$owner, $workspace] = $this->workspace();
        $this->actingAs($owner)->getJson(route('internal.integrations.callback', ['provider' => 'github', 'code' => 'secret-code', 'state' => 'tampered']))
            ->assertUnprocessable()->assertJsonValidationErrors('state');

        config(['plans.plans.starter.limits.integrations' => 4]);
        $connection = app(ConnectIntegration::class)->handle($workspace, $owner, IntegrationProvider::GITHUB, [
            'external_account_id' => 'github-42', 'account_name' => 'orbitra', 'access_token' => 'plain-access-token',
            'refresh_token' => 'plain-refresh-token', 'scopes' => ['read:user'],
        ]);

        $raw = DB::table('integration_connections')->where('id', $connection->id)->first();
        $this->assertNotSame('plain-access-token', $raw->access_token);
        $this->assertSame('plain-access-token', $connection->fresh()->access_token);
    }

    public function test_signed_oauth_state_connects_a_socialite_provider(): void
    {
        [$owner, $workspace] = $this->workspace();
        config(['services.github.client_id' => 'github-client', 'services.github.client_secret' => 'github-secret']);
        Socialite::fake('github', SocialiteUser::fake([
            'id' => 'github-88', 'name' => 'Orbitra Engineering', 'token' => 'github-token', 'refreshToken' => null,
        ]));

        $this->actingAs($owner)->get(route('internal.integrations.redirect', [
            'provider' => 'github', 'workspace_public_id' => $workspace->public_id,
        ]))->assertRedirect('https://socialite.fake/github/authorize')->assertSessionHas('integration.oauth');
        $oauth = session('integration.oauth');
        [$value, $signature] = explode('.', $oauth['state'], 2);
        $this->assertTrue(hash_equals(hash_hmac('sha256', $value, config('app.key')), $signature));

        $this->actingAs($owner)->get(route('internal.integrations.callback', [
            'provider' => 'github', 'code' => 'fake-code', 'state' => $oauth['state'],
        ]))->assertRedirect(route('app.settings.integrations', $workspace));
        $this->assertDatabaseHas('integration_connections', ['workspace_id' => $workspace->id, 'provider' => 'github', 'external_account_id' => 'github-88']);
    }

    public function test_expired_oauth_token_is_refreshed_before_provider_action(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
            'https://www.googleapis.com/drive/v3/files/*' => Http::response(['id' => 'drive-file-2', 'name' => 'Roadmap', 'webViewLink' => 'https://drive.google.com/file/d/drive-file-2/view']),
        ]);
        config(['services.google.client_id' => 'google-client', 'services.google.client_secret' => 'google-secret']);
        [$owner, $workspace] = $this->workspace();
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Roadmap', 'description' => null, 'color' => null, 'status' => ProjectStatus::ACTIVE->value,
            'start_date' => null, 'due_date' => null,
        ]);
        $connection = IntegrationConnection::create([
            'workspace_id' => $workspace->id, 'provider' => IntegrationProvider::GOOGLE_DRIVE, 'external_account_id' => 'google-2',
            'access_token' => 'expired-token', 'refresh_token' => 'refresh-token', 'expires_at' => now()->subMinute(), 'status' => 'connected',
        ]);

        app(IntegrationService::class)->linkDriveFile($connection, $workspace, $project, 'drive-file-2');
        $this->assertSame('fresh-token', $connection->fresh()->access_token);
        Http::assertSentCount(2);
    }

    public function test_each_provider_performs_its_real_workspace_action(): void
    {
        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '1.2']),
            'https://www.googleapis.com/drive/v3/files/*' => Http::response(['id' => 'drive-file-1', 'name' => 'Launch brief', 'mimeType' => 'application/pdf', 'webViewLink' => 'https://drive.google.com/file/d/drive-file-1/view']),
            'https://api.github.com/repos/orbitra/app' => Http::response(['full_name' => 'orbitra/app']),
            'https://api.zoom.us/v2/users/me/meetings' => Http::response(['id' => 9001, 'topic' => 'Planning', 'join_url' => 'https://zoom.us/j/9001', 'start_url' => 'https://zoom.us/s/9001']),
        ]);
        [$owner, $workspace] = $this->workspace();
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Launch', 'description' => null, 'color' => '#2eb0fb', 'status' => ProjectStatus::ACTIVE->value,
            'start_date' => null, 'due_date' => null,
        ]);
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $task = app(CreateTask::class)->handle($project, $owner, [
            'title' => 'Ship release', 'description' => null, 'status_public_id' => $status->public_id,
            'category_public_id' => null, 'priority' => TaskPriority::HIGH->value, 'start_at' => null, 'due_at' => null,
            'estimate_minutes' => null, 'assignee_public_ids' => [],
        ]);
        $event = ScheduleEvent::create([
            'workspace_id' => $workspace->id, 'project_id' => $project->id, 'creator_id' => $owner->id,
            'title' => 'Planning', 'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHour(), 'timezone' => 'UTC',
        ]);
        $connections = collect(IntegrationProvider::cases())->mapWithKeys(fn ($provider) => [$provider->value => IntegrationConnection::create([
            'workspace_id' => $workspace->id, 'provider' => $provider, 'external_account_id' => $provider->value.'-account',
            'account_name' => $provider->label(), 'access_token' => $provider->value.'-token', 'status' => 'connected',
        ])]);

        $this->actingAs($owner)->postJson(route('internal.integrations.action', $connections['slack']), ['channel' => '#delivery', 'message' => 'Release ready'])->assertOk();
        $this->actingAs($owner)->postJson(route('internal.integrations.action', $connections['google_drive']), ['project_public_id' => $project->public_id, 'file_id' => 'drive-file-1'])->assertOk();
        $this->actingAs($owner)->postJson(route('internal.integrations.action', $connections['github']), ['task_public_id' => $task->public_id, 'repository' => 'orbitra/app', 'url' => 'https://github.com/orbitra/app/pull/42'])->assertOk();
        $this->actingAs($owner)->postJson(route('internal.integrations.action', $connections['zoom']), ['schedule_event_public_id' => $event->public_id, 'topic' => 'Planning'])->assertOk();

        $this->assertDatabaseCount('integration_links', 3);
        $this->assertSame('https://zoom.us/j/9001', $event->fresh()->meeting_url);
        $this->assertSame('#delivery', data_get($connections['slack']->fresh()->settings_json, 'channel'));
        Http::assertSentCount(4);
    }

    public function test_disconnect_revokes_provider_token_and_removes_local_connection(): void
    {
        Http::fake(['https://oauth2.googleapis.com/revoke' => Http::response([], 200)]);
        [$owner, $workspace] = $this->workspace();
        $connection = IntegrationConnection::create([
            'workspace_id' => $workspace->id, 'provider' => IntegrationProvider::GOOGLE_DRIVE,
            'external_account_id' => 'google-1', 'account_name' => 'Drive', 'access_token' => 'token', 'status' => 'connected',
        ]);

        $this->actingAs($owner)->deleteJson(route('internal.integrations.destroy', $connection))->assertOk();
        $this->assertDatabaseMissing('integration_connections', ['id' => $connection->id]);
        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/revoke' && $request['token'] === 'token');
    }

    public function test_integrations_page_is_owner_only_and_has_all_provider_actions(): void
    {
        [$owner, $workspace] = $this->workspace();
        $this->actingAs($owner)->get(route('app.settings.integrations', $workspace))
            ->assertOk()->assertSee('Slack')->assertSee('Google Drive')->assertSee('GitHub')->assertSee('Zoom');

        $member = User::factory()->create();
        $workspace->memberships()->create(['user_id' => $member->id, 'role' => 'member', 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($member)->get(route('app.settings.integrations', $workspace))->assertForbidden();
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, ['name' => 'Orbitra Studio', 'timezone' => 'UTC', 'locale' => 'en', 'week_start' => 1]);

        return [$owner, $workspace];
    }
}
