<?php

namespace App\Services;

use App\Models\ScheduleEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ScheduleConflictService
{
    public function find(int $workspaceId, array $attendeeIds, CarbonInterface $start, CarbonInterface $end, User $viewer, ?int $exceptEventId = null): Collection
    {
        if ($attendeeIds === []) {
            return collect();
        }

        return ScheduleEvent::query()
            ->visibleTo($viewer)
            ->where('workspace_id', $workspaceId)
            ->when($exceptEventId, fn ($query) => $query->where('id', '!=', $exceptEventId))
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->whereHas('attendees', fn ($query) => $query->whereIn('users.id', $attendeeIds))
            ->with('attendees:id,public_id,first_name,last_name')
            ->orderBy('start_at')
            ->get();
    }
}
