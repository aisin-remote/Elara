<?php

namespace App\Actions\Schedule;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ScheduleConflictService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateScheduleEvent
{
    public function __construct(private readonly ScheduleConflictService $conflicts) {}

    public function handle(Workspace $workspace, User $creator, array $data, ?string $ipAddress = null): array
    {
        $projectId = isset($data['project_public_id'])
            ? Project::query()->where('workspace_id', $workspace->id)->where('public_id', $data['project_public_id'])->value('id')
            : null;
        $attendeeIds = $workspace->memberships()->active()->whereHas('user', fn ($query) => $query
            ->whereIn('public_id', $data['attendee_public_ids'] ?? []))->pluck('user_id')->all();
        $attendeeIds = array_values(array_unique([
            ...$attendeeIds,
            ...array_map('intval', $data['additional_attendee_ids'] ?? []),
        ]));
        $start = Carbon::parse($data['start_at'], $workspace->timezone)->utc();
        $end = Carbon::parse($data['end_at'], $workspace->timezone)->utc();

        return DB::transaction(function () use ($workspace, $creator, $data, $projectId, $attendeeIds, $start, $end, $ipAddress) {
            $event = $workspace->scheduleEvents()->create([
                'project_id' => $projectId,
                'creator_id' => $creator->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'start_at' => $start,
                'end_at' => $end,
                'timezone' => $workspace->timezone,
                'color' => $data['color'] ?? null,
                'meeting_url' => $data['meeting_url'] ?? null,
            ]);
            $event->attendees()->sync($attendeeIds);
            ActivityLog::record($workspace, $event, 'schedule.created', $creator, ipAddress: $ipAddress);

            return [
                'event' => $event->load(['project', 'attendees']),
                'conflicts' => $this->conflicts->find($workspace->id, $attendeeIds, $start, $end, $creator, $event->id),
            ];
        });
    }
}
