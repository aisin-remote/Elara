<?php

namespace App\Actions\MeetingMinute;

use App\Models\MeetingMinute;
use App\Models\User;

class RecordMeetingMinuteRevision
{
    public function handle(MeetingMinute $minute, User $editor): void
    {
        $minute->load('items');

        $minute->revisions()->create([
            'editor_id' => $editor->id,
            'revision' => ((int) $minute->revisions()->max('revision')) + 1,
            'snapshot_json' => [
                'title' => $minute->title,
                'meeting_at' => $minute->meeting_at?->toIso8601String(),
                'summary' => $minute->summary,
                'project_id' => $minute->project_id,
                'publication_status' => $minute->publication_status->value,
                'items' => $minute->items->map(fn ($item) => [
                    'content' => $item->content,
                    'pic_name' => $item->pic_name,
                    'pic_user_id' => $item->pic_user_id,
                    'due_date' => $item->due_date?->toDateString(),
                    'status' => $item->status->value,
                ])->all(),
            ],
            'created_at' => now(),
        ]);
    }
}
