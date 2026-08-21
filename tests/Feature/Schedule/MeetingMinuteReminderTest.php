<?php

namespace Tests\Feature\Schedule;

use App\Actions\Workspace\CreateWorkspace;
use App\Enums\MeetingMinutePublicationStatus;
use App\Enums\MeetingMinuteStatus;
use App\Models\MeetingMinute;
use App\Models\User;
use App\Notifications\OrbitraNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MeetingMinuteReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_and_tba_follow_ups_are_reminded_once(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, ['name' => 'Delivery', 'timezone' => 'Asia/Jakarta', 'locale' => 'en', 'week_start' => 1]);
        $minute = MeetingMinute::create([
            'workspace_id' => $workspace->id, 'creator_id' => $owner->id, 'title' => 'Review',
            'meeting_at' => now()->subDays(4), 'publication_status' => MeetingMinutePublicationStatus::PUBLISHED,
            'published_at' => now()->subDays(4), 'published_by' => $owner->id,
        ]);
        $due = $minute->items()->create(['content' => 'Send decision', 'pic_name' => $owner->name, 'pic_user_id' => $owner->id, 'due_date' => today('Asia/Jakarta')->addDay(), 'status' => MeetingMinuteStatus::OUTSTANDING, 'position' => 1024]);
        $tba = $minute->items()->create(['content' => 'Confirm owner', 'pic_name' => $owner->name, 'pic_user_id' => $owner->id, 'status' => MeetingMinuteStatus::OUTSTANDING, 'position' => 2048]);

        $this->artisan('orbitra:send-mom-reminders')->assertSuccessful();
        Notification::assertSentToTimes($owner, OrbitraNotification::class, 2);
        $this->assertNotNull($due->fresh()->due_reminded_at);
        $this->assertNotNull($tba->fresh()->tba_reminded_at);

        $this->artisan('orbitra:send-mom-reminders')->assertSuccessful();
        Notification::assertSentToTimes($owner, OrbitraNotification::class, 2);
    }
}
