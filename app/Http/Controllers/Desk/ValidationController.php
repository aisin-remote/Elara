<?php

namespace App\Http\Controllers\Desk;

use App\Actions\File\StorePrivateFile;
use App\Actions\Validation\RespondToCheckpoint;
use App\Enums\CheckpointStatus;
use App\Http\Controllers\Controller;
use App\Models\ValidationCheckpoint;
use App\Services\MeetingMinuteActionItems;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ValidationController extends Controller
{
    public function index(Request $request, MeetingMinuteActionItems $meetingMinuteActionItems): View
    {
        $checkpoints = ValidationCheckpoint::query()
            ->visibleTo($request->user())
            ->with(['task', 'subject'])
            ->orderByRaw("case when status = 'open' then 0 else 1 end")
            ->orderBy('expires_at')
            ->get();

        return view('desk.validations.index', [
            'open' => $checkpoints->where('status', CheckpointStatus::OPEN),
            'answered' => $checkpoints->where('status', '!=', CheckpointStatus::OPEN),
            'momActionItems' => $meetingMinuteActionItems->forUser($request->user(), requesterWorkspace: $request->user()->workspaceMemberships()->active()->with('workspace')->first()?->workspace, limit: 20),
        ]);
    }

    public function respond(Request $request, ValidationCheckpoint $checkpoint, RespondToCheckpoint $action, StorePrivateFile $storeFile): RedirectResponse
    {
        $this->authorize('respond', $checkpoint);

        $data = $request->validate([
            'decision' => ['required', 'in:approved,changes_requested'],
            'response_note' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:'.config('orbitra.max_file_upload_kb'), 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip'],
        ]);

        // Filed against the task so ITD finds them where the work is, and marked shared so the
        // requester keeps seeing their own upload on the request page.
        foreach ($request->file('attachments', []) as $upload) {
            $storeFile->handle(
                $checkpoint->workspace,
                $request->user(),
                $upload,
                task: $checkpoint->task,
                metadata: ['request_shared' => true],
            );
        }

        $action->handle(
            $checkpoint,
            $request->user(),
            CheckpointStatus::from($data['decision']),
            $data['response_note'] ?? null,
        );

        $files = count($request->file('attachments', []));
        $sent = $files === 1 ? ' 1 file sent.' : ($files > 1 ? ' '.$files.' files sent.' : '');

        return redirect()
            ->route('desk.validations.index')
            ->with('status', ($checkpoint->isInformationRequest()
                ? 'Answer sent to ITD.'
                : ($data['decision'] === 'approved'
                    ? 'Confirmed. The team carries on.'
                    : 'Sent back to the team with your note.')).$sent);
    }
}
