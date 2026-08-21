<?php

namespace App\Services;

use App\Enums\MeetingMinutePublicationStatus;
use App\Enums\MeetingMinuteStatus;
use App\Models\MeetingMinuteItem;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

class MeetingMinuteActionItems
{
    public function forUser(User $user, ?Workspace $deliveryWorkspace = null, ?Workspace $requesterWorkspace = null, int $limit = 5): array
    {
        $query = MeetingMinuteItem::query()
            ->where('pic_user_id', $user->id)
            ->where('status', '!=', MeetingMinuteStatus::DONE->value)
            ->whereHas('meetingMinute', fn (Builder $minute) => $minute
                ->whereIn('publication_status', [MeetingMinutePublicationStatus::PUBLISHED->value, MeetingMinutePublicationStatus::LOCKED->value])
                ->when($deliveryWorkspace, fn (Builder $scoped) => $scoped->where('workspace_id', $deliveryWorkspace->id)))
            ->with(['meetingMinute.project'])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('position');

        $total = (clone $query)->count();
        $items = $query->limit($limit)->get()->map(function (MeetingMinuteItem $item) use ($requesterWorkspace): array {
            $minute = $item->meetingMinute;

            return [
                'public_id' => $item->public_id,
                'content' => $item->content,
                'minute' => $minute->title,
                'project' => $minute->project?->name ?? 'General',
                'due' => $item->due_date?->format('M j, Y') ?? 'TBA',
                'overdue' => $item->due_date?->isPast() ?? false,
                'status' => $item->status,
                'url' => $requesterWorkspace
                    ? route('desk.schedule.mom.show', [$requesterWorkspace, $minute->public_id])
                    : route('app.schedule.minutes.show', [$minute->workspace, $minute]),
            ];
        })->all();

        return ['total' => $total, 'items' => $items];
    }
}
