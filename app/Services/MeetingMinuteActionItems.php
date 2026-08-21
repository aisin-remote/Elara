<?php

namespace App\Services;

use App\Enums\MeetingMinutePublicationStatus;
use App\Enums\MeetingMinuteStatus;
use App\Models\MeetingMinuteItem;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MeetingMinuteActionItems
{
    public function forUser(User $user, ?Workspace $deliveryWorkspace = null, ?Workspace $requesterWorkspace = null, int $limit = 5): array
    {
        return $this->forUsers(collect([$user]), $user, $deliveryWorkspace, $requesterWorkspace, $limit);
    }

    /** @param Collection<int, User> $users */
    public function forUsers(Collection $users, User $viewer, ?Workspace $deliveryWorkspace = null, ?Workspace $requesterWorkspace = null, int $limit = 5): array
    {
        $query = MeetingMinuteItem::query()
            ->whereIn('pic_user_id', $users->pluck('id'))
            ->where('status', '!=', MeetingMinuteStatus::DONE->value)
            ->whereHas('meetingMinute', fn (Builder $minute) => $minute
                ->whereIn('publication_status', [MeetingMinutePublicationStatus::PUBLISHED->value, MeetingMinutePublicationStatus::LOCKED->value])
                ->when($deliveryWorkspace, fn (Builder $scoped) => $scoped->where('workspace_id', $deliveryWorkspace->id)))
            ->with(['meetingMinute.project', 'pic'])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('position');

        $total = (clone $query)->count();
        $items = $query->limit($limit)->get()->map(function (MeetingMinuteItem $item) use ($requesterWorkspace, $viewer): array {
            $minute = $item->meetingMinute;

            return [
                'public_id' => $item->public_id,
                'content' => $item->content,
                'minute' => $minute->title,
                'project' => $minute->project?->name ?? 'General',
                'due' => $item->due_date?->format('M j, Y') ?? 'TBA',
                'overdue' => $item->due_date?->isPast() ?? false,
                'status' => $item->status,
                'pic_name' => $item->pic?->name ?? $item->pic_name,
                'can_update' => $viewer->can('update', $item),
                'url' => $requesterWorkspace
                    ? route('desk.schedule.mom.show', [$requesterWorkspace, $minute->public_id])
                    : route('app.schedule.minutes.show', [$minute->workspace, $minute]),
            ];
        })->all();

        return ['total' => $total, 'items' => $items];
    }
}
