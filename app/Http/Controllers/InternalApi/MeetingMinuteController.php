<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\File\DeleteFile;
use App\Actions\File\StorePrivateFile;
use App\Actions\MeetingMinute\RecordMeetingMinuteRevision;
use App\Actions\MeetingMinute\SaveMeetingMinute;
use App\Enums\MeetingMinutePublicationStatus;
use App\Http\Requests\MeetingMinute\SaveMeetingMinuteRequest;
use App\Http\Requests\MeetingMinute\UpdateMeetingMinuteItemRequest;
use App\Http\Requests\MeetingMinute\UpdateMeetingMinutePublicationRequest;
use App\Models\ActivityLog;
use App\Models\MeetingMinute;
use App\Models\MeetingMinuteItem;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MeetingMinuteController extends Controller
{
    public function store(SaveMeetingMinuteRequest $request, Workspace $workspace, SaveMeetingMinute $save, StorePrivateFile $storeFile): JsonResponse|RedirectResponse
    {
        $meetingMinute = $save->handle($workspace, $request->user(), $request->validated(), null, $request->ip());
        $this->storeAttachments($request, $meetingMinute, $storeFile);

        return $this->success(
            $request,
            ['public_id' => $meetingMinute->public_id],
            'Meeting minutes created.',
            route('app.schedule.minutes.show', [$workspace, $meetingMinute]),
            201,
        );
    }

    public function update(SaveMeetingMinuteRequest $request, MeetingMinute $meetingMinute, SaveMeetingMinute $save, StorePrivateFile $storeFile): JsonResponse|RedirectResponse
    {
        $meetingMinute = $save->handle($meetingMinute->workspace, $request->user(), $request->validated(), $meetingMinute, $request->ip());
        $this->storeAttachments($request, $meetingMinute, $storeFile);

        return $this->success(
            $request,
            ['public_id' => $meetingMinute->public_id],
            'Meeting minutes updated.',
            route('app.schedule.minutes.show', [$meetingMinute->workspace, $meetingMinute]),
        );
    }

    public function destroy(Request $request, MeetingMinute $meetingMinute, DeleteFile $deleteFile): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $meetingMinute);
        $workspace = $meetingMinute->workspace;

        $meetingMinute->files()->each(fn ($file) => $deleteFile->handle($file));
        $meetingMinute->delete();

        ActivityLog::record($workspace, $meetingMinute, 'meeting_minute.deleted', $request->user(), [], $request->ip());

        return $this->success($request, null, 'Meeting minutes deleted.', route('app.schedule.minutes.index', $workspace));
    }

    public function publication(UpdateMeetingMinutePublicationRequest $request, MeetingMinute $meetingMinute, RecordMeetingMinuteRevision $revisions): JsonResponse|RedirectResponse
    {
        $target = MeetingMinutePublicationStatus::from($request->validated('publication_status'));
        $allowed = match ($meetingMinute->publication_status) {
            MeetingMinutePublicationStatus::DRAFT => [$target === MeetingMinutePublicationStatus::PUBLISHED],
            MeetingMinutePublicationStatus::PUBLISHED => [$target === MeetingMinutePublicationStatus::LOCKED],
            MeetingMinutePublicationStatus::LOCKED => [$target === MeetingMinutePublicationStatus::PUBLISHED],
        };
        abort_unless($allowed[0], 422, 'This MOM lifecycle change is not allowed.');

        $meetingMinute->publication_status = $target;
        if ($target === MeetingMinutePublicationStatus::PUBLISHED) {
            $meetingMinute->published_at ??= now();
            $meetingMinute->published_by ??= $request->user()->id;
            $meetingMinute->locked_at = null;
            $meetingMinute->locked_by = null;
        } else {
            $meetingMinute->locked_at = now();
            $meetingMinute->locked_by = $request->user()->id;
        }
        $meetingMinute->save();
        $revisions->handle($meetingMinute, $request->user());
        ActivityLog::record($meetingMinute->workspace, $meetingMinute, 'meeting_minute.'.$target->value, $request->user(), [], $request->ip());

        return $this->success($request, null, 'MOM is now '.$target->label().'.', route('app.schedule.minutes.show', [$meetingMinute->workspace, $meetingMinute]));
    }

    public function updateItem(UpdateMeetingMinuteItemRequest $request, MeetingMinuteItem $meetingMinuteItem): JsonResponse|RedirectResponse
    {
        $meetingMinuteItem->update([
            'status' => $request->validated('status'),
            'due_reminded_at' => null,
            'overdue_reminded_at' => null,
        ]);
        ActivityLog::record($meetingMinuteItem->meetingMinute->workspace, $meetingMinuteItem->meetingMinute, 'meeting_minute.item_updated', $request->user(), [
            'item' => $meetingMinuteItem->public_id,
            'status' => $request->validated('status'),
        ], $request->ip());

        return $this->success($request, null, 'Action item updated.', url()->previous());
    }

    private function storeAttachments(SaveMeetingMinuteRequest $request, MeetingMinute $meetingMinute, StorePrivateFile $storeFile): void
    {
        foreach ($request->file('attachments', []) as $upload) {
            $file = $storeFile->handle($meetingMinute->workspace, $request->user(), $upload);
            $meetingMinute->files()->save($file);

            ActivityLog::record($meetingMinute->workspace, $meetingMinute, 'meeting_minute.attachment_added', $request->user(), [
                'file' => $file->original_name,
            ], $request->ip());
        }
    }
}
