<?php

namespace Tests\Feature\Notification;

use App\Actions\Project\CreateProject;
use App\Actions\Task\CreateTask;
use App\Actions\Workspace\CreateWorkspace;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Notifications\OrbitraNotification;
use App\Services\NotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_are_stored_per_workspace_for_every_channel(): void
    {
        [$user, $workspace] = $this->workspace();
        $response = $this->actingAs($user)->patchJson(route('internal.notification-preferences.update'), [
            'workspace_public_id' => $workspace->public_id,
            'preferences' => ['task_assigned' => ['mail' => true, 'in_app' => false, 'push' => true]],
        ])->assertOk();

        $response->assertJsonPath('data.task_assigned.mail', true)->assertJsonPath('data.task_assigned.in_app', false);
        $this->assertDatabaseCount('notification_preferences', 4);
        $this->assertDatabaseHas('notification_preferences', ['channel' => 'push', 'event' => 'task_assigned', 'enabled' => true]);
    }

    public function test_notification_center_counts_and_marks_database_notifications_read(): void
    {
        [$user, $workspace] = $this->workspace();
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => OrbitraNotification::class,
            'data' => ['event' => 'project_updated', 'workspace_public_id' => $workspace->public_id, 'title' => 'Project updated', 'body' => 'A project changed.', 'url' => '/app'],
        ]);

        $this->actingAs($user)->getJson(route('internal.notifications.index', ['workspace_public_id' => $workspace->public_id]))
            ->assertOk()->assertJsonPath('meta.unread_count', 1)->assertJsonPath('data.0.title', 'Project updated');
        $this->actingAs($user)->postJson(route('internal.notifications.read', $notification->id))->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
        $this->actingAs($user)->postJson(route('internal.notifications.read-all'))->assertOk();
    }

    public function test_preference_service_selects_queued_mail_database_broadcast_and_push_channels(): void
    {
        Notification::fake();
        [$user, $workspace] = $this->workspace();
        app(NotificationPreferenceService::class)->update($user, $workspace, [
            'task_assigned' => ['mail' => true, 'in_app' => true, 'push' => true],
        ]);
        app(NotificationPreferenceService::class)->notify($user, $workspace, 'task_assigned', 'Assigned', 'A task is waiting.', '/app');

        Notification::assertSentTo($user, OrbitraNotification::class, function ($notification, $channels) use ($user) {
            $via = $notification->via($user);

            return $notification instanceof ShouldQueue
                && in_array('mail', $via, true)
                && in_array('database', $via, true)
                && in_array('broadcast', $via, true)
                && in_array(WebPushChannel::class, $via, true);
        });
    }

    public function test_push_subscription_and_notification_settings_page_work(): void
    {
        [$user, $workspace] = $this->workspace();
        $endpoint = 'https://push.example.test/subscription/123';
        $this->actingAs($user)->postJson(route('internal.push-subscriptions.store'), [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
            'content_encoding' => 'aesgcm',
        ])->assertCreated();
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => $endpoint, 'subscribable_id' => $user->id]);
        $this->actingAs($user)->deleteJson(route('internal.push-subscriptions.destroy'), ['endpoint' => $endpoint])->assertOk();
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $endpoint]);
        $this->actingAs($user)->get(route('app.settings.notifications', $workspace))
            ->assertOk()->assertSee('Choose what reaches you')->assertSee('Browser push notifications');
    }

    public function test_task_assignment_uses_the_recipient_preferences(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->workspace();
        $assignee = User::factory()->create();
        $workspace->memberships()->create([
            'user_id' => $assignee->id, 'role' => WorkspaceRole::MEMBER, 'status' => WorkspaceMemberStatus::ACTIVE, 'joined_at' => now(),
        ]);
        $project = app(CreateProject::class)->handle($workspace, $owner, [
            'name' => 'Launch', 'description' => null, 'color' => '#4f46e5', 'status' => ProjectStatus::ACTIVE->value,
            'start_date' => null, 'due_date' => null,
        ]);
        $project->memberships()->create(['user_id' => $assignee->id, 'role' => ProjectMemberRole::MEMBER]);
        $status = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();

        app(CreateTask::class)->handle($project, $owner, [
            'title' => 'Prepare release', 'description' => null, 'status_public_id' => $status->public_id,
            'category_public_id' => null, 'priority' => TaskPriority::HIGH->value, 'start_at' => null, 'due_at' => null,
            'estimate_minutes' => null, 'assignee_public_ids' => [$assignee->public_id],
        ]);

        Notification::assertSentTo($assignee, OrbitraNotification::class, fn ($notification) => $notification->toArray($assignee)['event'] === 'task_assigned');
    }

    private function workspace(): array
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, [
            'name' => 'Orbitra Studio', 'timezone' => 'Asia/Jakarta', 'locale' => 'en', 'week_start' => 1,
        ]);

        return [$user, $workspace];
    }
}
