<?php

namespace App\Console\Commands;

use App\Enums\MeetingMinutePublicationStatus;
use App\Enums\MeetingMinuteStatus;
use App\Enums\WorkspaceRole;
use App\Models\MeetingMinuteItem;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SendMeetingMinuteReminders extends Command
{
    protected $signature = 'orbitra:send-mom-reminders';

    protected $description = 'Notify MOM owners about due, overdue, and unresolved TBA action items.';

    public function handle(NotificationPreferenceService $notifications): int
    {
        MeetingMinuteItem::query()
            ->where('status', '!=', MeetingMinuteStatus::DONE->value)
            ->whereHas('meetingMinute', fn (Builder $minute) => $minute->whereIn('publication_status', [
                MeetingMinutePublicationStatus::PUBLISHED->value,
                MeetingMinutePublicationStatus::LOCKED->value,
            ]))
            ->with(['pic', 'meetingMinute.workspace', 'meetingMinute.creator'])
            ->chunkById(100, function ($items) use ($notifications): void {
                foreach ($items as $item) {
                    $timezone = $item->meetingMinute->workspace->timezone ?: config('app.timezone');
                    $today = today($timezone);

                    if ($item->due_date?->isSameDay($today->copy()->addDay()) && ! $item->due_reminded_at && $item->pic) {
                        $this->notify($notifications, $item->pic, $item, 'MOM action due tomorrow', '“'.$item->content.'” is due tomorrow.');
                        $item->forceFill(['due_reminded_at' => now()])->save();
                    } elseif ($item->due_date?->isBefore($today) && ! $item->overdue_reminded_at && $item->pic) {
                        $this->notify($notifications, $item->pic, $item, 'MOM action overdue', '“'.$item->content.'” is overdue.');
                        $item->forceFill(['overdue_reminded_at' => now()])->save();
                    } elseif ($item->due_date === null && ! $item->tba_reminded_at && $item->meetingMinute->meeting_at->lt(now()->subDays(3))) {
                        $this->notify($notifications, $item->meetingMinute->creator, $item, 'MOM due date still TBA', 'Set a due date for “'.$item->content.'” or keep it intentionally TBA.');
                        $item->forceFill(['tba_reminded_at' => now()])->save();
                    }
                }
            });

        return self::SUCCESS;
    }

    private function notify(NotificationPreferenceService $notifications, User $recipient, MeetingMinuteItem $item, string $title, string $body): void
    {
        $minute = $item->meetingMinute;
        $deliveryMembership = $minute->workspace->memberships()->active()->where('user_id', $recipient->id)->first();
        $url = $deliveryMembership?->role->canAccessDeliveryDesk()
            ? route('app.schedule.minutes.show', [$minute->workspace, $minute])
            : $this->requesterUrl($recipient, $minute->public_id);

        if ($url) {
            $notifications->notify($recipient, $minute->workspace, 'mom_action_item', $title, $body, $url, ['meeting_minute_item' => $item->public_id]);
        }
    }

    private function requesterUrl(User $user, string $minutePublicId): ?string
    {
        $membership = $user->workspaceMemberships()->active()->where('role', WorkspaceRole::REQUESTER->value)->with('workspace')->first();

        return $membership ? route('desk.schedule.mom.show', [$membership->workspace, $minutePublicId]) : null;
    }
}
