<?php

namespace App\Http\Controllers\App;

use App\Actions\Request\TransitionProjectRequest;
use App\Actions\Schedule\CreateScheduleEvent;
use App\Enums\ProjectRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ProjectRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectApprovalController extends Controller
{
    public function show(Request $request, Workspace $workspace, ProjectRequest $projectRequest): View
    {
        abort_unless($projectRequest->workspace_id === $workspace->id, 404);
        $this->authorize('view', $projectRequest);

        return view('app.approvals.project-show', [
            'workspace' => $workspace,
            'request' => $projectRequest->load(['requester', 'supervisor', 'manager', 'meeting.attendees', 'project']),
            'canRunMeeting' => $request->user()->can('runMeeting', $projectRequest),
            'canSignSpv' => $request->user()->can('signAsSupervisor', $projectRequest),
            'canSignManager' => $request->user()->can('signAsManager', $projectRequest),
            'breakdown' => $projectRequest->breakdowns()->with('acceptedBy')->latest('id')->first(),
            'timeline' => ActivityLog::where('subject_type', $projectRequest->getMorphClass())
                ->where('subject_id', $projectRequest->id)
                ->with('actor')
                ->latest('created_at')
                ->get(),
        ]);
    }

    /** Books the scoping meeting using the schedule the workspace already runs on. */
    public function scheduleMeeting(Request $request, Workspace $workspace, ProjectRequest $projectRequest, CreateScheduleEvent $createEvent): RedirectResponse
    {
        abort_unless($projectRequest->workspace_id === $workspace->id, 404);
        $this->authorize('runMeeting', $projectRequest);

        $data = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'meeting_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $result = $createEvent->handle($workspace, $request->user(), [
            'title' => 'Scoping: '.$projectRequest->title,
            'description' => 'Scoping meeting for a new project request.',
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'meeting_url' => $data['meeting_url'] ?? null,
            'attendee_public_ids' => [$projectRequest->requester->public_id, $request->user()->public_id],
            'additional_attendee_ids' => [$projectRequest->requester_id],
        ], $request->ip());

        $projectRequest->update(['schedule_event_id' => $result['event']->id]);

        return back()->with('status', 'Meeting scheduled and both attendees invited.');
    }

    /** Records that the meeting happened. Only then does a signature become possible. */
    public function markMeetingHeld(Request $request, Workspace $workspace, ProjectRequest $projectRequest, TransitionProjectRequest $transition): RedirectResponse
    {
        abort_unless($projectRequest->workspace_id === $workspace->id, 404);
        $this->authorize('runMeeting', $projectRequest);

        $data = $request->validate([
            'meeting_note' => ['required', 'string', 'min:20', 'max:4000'],
        ]);

        $projectRequest->update([
            'meeting_held_at' => now(),
            'meeting_note' => $data['meeting_note'],
        ]);

        ActivityLog::record($workspace, $projectRequest, 'project_request.meeting_held', $request->user());

        $transition->handle($projectRequest->fresh(), ProjectRequestStatus::PENDING_SPV, $request->user());

        return back()->with('status', 'Meeting recorded. The request is ready for your signature.');
    }

    public function decide(Request $request, Workspace $workspace, ProjectRequest $projectRequest, TransitionProjectRequest $transition): RedirectResponse
    {
        abort_unless($projectRequest->workspace_id === $workspace->id, 404);

        $stage = $projectRequest->status;
        $ability = $stage === ProjectRequestStatus::PENDING_MANAGER ? 'signAsManager' : 'signAsSupervisor';
        $this->authorize($ability, $projectRequest);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject,needs_info'],
            'note' => ['nullable', 'string', 'max:2000'],
            // Only the second signature schedules, so only it needs the effort figure.
            'estimated_hours' => [
                $stage === ProjectRequestStatus::PENDING_MANAGER && $request->input('decision') === 'approve' ? 'required' : 'nullable',
                'numeric', 'min:1', 'max:2000',
            ],
        ]);

        if (isset($data['estimated_hours'])) {
            $projectRequest->update(['estimated_minutes' => (int) round($data['estimated_hours'] * 60)]);
        }

        $next = match ($data['decision']) {
            'approve' => $stage === ProjectRequestStatus::PENDING_MANAGER
                ? ProjectRequestStatus::APPROVED
                : ProjectRequestStatus::PENDING_MANAGER,
            'reject' => ProjectRequestStatus::REJECTED,
            default => ProjectRequestStatus::NEEDS_INFO,
        };

        $transition->handle($projectRequest, $next, $request->user(), $data['note'] ?? null);

        return redirect()
            ->route('app.approvals.index', $workspace)
            ->with('status', match ($next) {
                ProjectRequestStatus::PENDING_MANAGER => 'Signed. It now needs a manager to countersign.',
                ProjectRequestStatus::APPROVED => 'Approved. The project has been created.',
                ProjectRequestStatus::REJECTED => 'Rejected, and the requester has been told why.',
                default => 'Sent back to the requester for more detail.',
            });
    }
}
