<?php

namespace App\Actions\MeetingMinute;

use App\Models\ActivityLog;
use App\Models\MeetingMinute;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class SaveMeetingMinute
{
    public function handle(Workspace $workspace, User $actor, array $data, ?MeetingMinute $meetingMinute = null, ?string $ipAddress = null): MeetingMinute
    {
        return DB::transaction(function () use ($workspace, $actor, $data, $meetingMinute, $ipAddress): MeetingMinute {
            $creating = $meetingMinute === null;
            $meetingMinute ??= new MeetingMinute([
                'workspace_id' => $workspace->id,
                'creator_id' => $actor->id,
            ]);

            $scheduleEvent = filled($data['schedule_event_public_id'] ?? null)
                ? ScheduleEvent::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('public_id', $data['schedule_event_public_id'])
                    ->firstOrFail()
                : null;
            $project = filled($data['project_public_id'] ?? null)
                ? Project::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('public_id', $data['project_public_id'])
                    ->firstOrFail()
                : null;

            $meetingMinute->fill([
                'schedule_event_id' => $scheduleEvent?->id,
                'project_id' => $project?->id,
                'title' => $data['title'],
                'meeting_at' => $data['meeting_at'],
                'summary' => $data['summary'] ?? null,
            ])->save();

            if (! $creating) {
                $meetingMinute->items()->delete();
            }

            foreach ($data['items'] as $position => $item) {
                $pic = filled($item['pic_user_public_id'] ?? null)
                    ? User::query()->where('public_id', $item['pic_user_public_id'])->firstOrFail()
                    : null;

                $meetingMinute->items()->create([
                    'content' => $item['content'],
                    'pic_name' => $pic?->name ?? $item['pic_name'],
                    'pic_user_id' => $pic?->id,
                    'due_date' => $item['due_date'] ?? null,
                    'status' => $item['status'],
                    'position' => ($position + 1) * 1024,
                ]);
            }

            ActivityLog::record(
                $workspace,
                $meetingMinute,
                $creating ? 'meeting_minute.created' : 'meeting_minute.updated',
                $actor,
                ['items' => count($data['items'])],
                $ipAddress,
            );

            return $meetingMinute;
        });
    }
}
