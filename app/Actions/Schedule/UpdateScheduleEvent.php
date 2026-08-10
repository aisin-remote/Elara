<?php

namespace App\Actions\Schedule;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\ScheduleConflictService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateScheduleEvent
{
    public function __construct(private readonly ScheduleConflictService $conflicts) {}

    public function handle(ScheduleEvent $event, User $actor, array $data, int $version, ?string $ipAddress = null): ?array
    {
        $projectId = isset($data['project_public_id'])
            ? Project::query()->where('workspace_id', $event->workspace_id)->where('public_id', $data['project_public_id'])->value('id')
            : null;
        $attendeeIds = $event->workspace->memberships()->active()->whereHas('user', fn ($query) => $query
            ->whereIn('public_id', $data['attendee_public_ids'] ?? []))->pluck('user_id')->all();
        $start = Carbon::parse($data['start_at'], $event->workspace->timezone)->utc();
        $end = Carbon::parse($data['end_at'], $event->workspace->timezone)->utc();

        return DB::transaction(function () use ($event, $actor, $data, $version, $projectId, $attendeeIds, $start, $end, $ipAddress) {
            $updated = ScheduleEvent::query()->whereKey($event->id)->where('version', $version)->update([
                'project_id' => $projectId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'start_at' => $start,
                'end_at' => $end,
                'timezone' => $event->workspace->timezone,
                'color' => $data['color'] ?? null,
                'meeting_url' => $data['meeting_url'] ?? null,
                'version' => DB::raw('version + 1'),
            ]);

            if (! $updated) {
                return null;
            }

            $event = $event->fresh();
            $event->attendees()->sync($attendeeIds);
            ActivityLog::record($event->workspace, $event, 'schedule.updated', $actor, ipAddress: $ipAddress);

            return [
                'event' => $event->load(['project', 'attendees']),
                'conflicts' => $this->conflicts->find($event->workspace_id, $attendeeIds, $start, $end, $actor, $event->id),
            ];
        });
    }
}
