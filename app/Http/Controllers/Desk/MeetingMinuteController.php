<?php

namespace App\Http\Controllers\Desk;

use App\Actions\File\StorePrivateFile;
use App\Actions\MeetingMinute\SaveMeetingMinute;
use App\Enums\MeetingMinuteStatus;
use App\Enums\ProjectType;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\DraftRequesterMeetingSummaryRequest;
use App\Http\Requests\MeetingMinute\SaveRequesterMeetingMinuteRequest;
use App\Models\ActivityLog;
use App\Models\MeetingMinute;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectRequest;
use App\Models\ScheduleEvent;
use App\Models\Workspace;
use App\Services\Ai\OpenAiMeetingSummary;
use App\Services\DepartmentWorkspaceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeetingMinuteController extends Controller
{
    public function create(Request $request, Workspace $workspace, string $event, DepartmentWorkspaceService $workspaces): View|RedirectResponse
    {
        $this->authorizeRequesterWorkspace($workspace);
        $deliveryWorkspace = $workspaces->deliveryWorkspace();
        $scheduleEvent = $this->event($request, $deliveryWorkspace, $event)->load(['attendees', 'meetingMinute']);

        if ($scheduleEvent->meetingMinute) {
            return redirect()->route('desk.schedule.mom.show', [$workspace, $scheduleEvent->meetingMinute->public_id]);
        }

        $departmentId = $workspace->organization_department_id;
        $projects = Project::query()
            ->where('workspace_id', $deliveryWorkspace->id)
            ->where('type', '!=', ProjectType::PERSONAL->value)
            ->whereNull('archived_at')
            ->when(config('organization.required') && $departmentId, fn (Builder $query) => $query->where(fn (Builder $visible) => $visible
                ->where('type', ProjectType::SYSTEM->value)
                ->orWhereIn('id', ProjectRequest::query()
                    ->select('project_id')
                    ->where('workspace_id', $deliveryWorkspace->id)
                    ->where('requester_department_external_id', $departmentId)
                    ->whereNotNull('project_id'))))
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'type']);
        $picUsers = $deliveryWorkspace->memberships()->active()->whereHas('user')->with('user')->get()->pluck('user')
            ->merge($scheduleEvent->attendees)->unique('id')->sortBy(fn ($user) => strtolower($user->name))->values();
        $formItems = old('items');
        if (! is_array($formItems)) {
            $formItems = [[
                'content' => '', 'pic_name' => '', 'pic_user_public_id' => null,
                'due_date' => null, 'status' => MeetingMinuteStatus::OUTSTANDING->value,
            ]];
        }

        return view('desk.schedule.mom.create', [
            'requesterWorkspace' => $workspace,
            'workspace' => $deliveryWorkspace,
            'meetingMinute' => null,
            'scheduleEvent' => $scheduleEvent,
            'projects' => $projects,
            'picUsers' => $picUsers,
            'statuses' => MeetingMinuteStatus::cases(),
            'formItems' => array_values($formItems),
            'aiSummaryUrl' => route('desk.schedule.mom.summary', [$workspace, $event]),
            'formAction' => route('desk.schedule.mom.store', [$workspace, $event]),
            'formMethod' => 'POST',
            'cancelUrl' => route('desk.schedule.index', $workspace),
            'submitLabel' => 'Create MOM',
        ]);
    }

    public function store(
        SaveRequesterMeetingMinuteRequest $request,
        Workspace $workspace,
        string $event,
        DepartmentWorkspaceService $workspaces,
        SaveMeetingMinute $save,
        StorePrivateFile $storeFile,
    ): RedirectResponse {
        $deliveryWorkspace = $workspaces->deliveryWorkspace();
        $minute = $save->handle($deliveryWorkspace, $request->user(), [
            ...$request->validated(),
            'schedule_event_public_id' => $event,
        ], null, $request->ip());

        foreach ($request->file('attachments', []) as $upload) {
            $file = $storeFile->handle($deliveryWorkspace, $request->user(), $upload);
            $minute->files()->save($file);
            ActivityLog::record($deliveryWorkspace, $minute, 'meeting_minute.attachment_added', $request->user(), ['file' => $file->original_name], $request->ip());
        }

        return redirect()->route('desk.schedule.mom.show', [$workspace, $minute->public_id])
            ->with('status', 'MOM created.');
    }

    public function show(Request $request, Workspace $workspace, string $meetingMinute, DepartmentWorkspaceService $workspaces): View
    {
        $this->authorizeRequesterWorkspace($workspace);
        $minute = MeetingMinute::query()
            ->where('workspace_id', $workspaces->deliveryWorkspace()->id)
            ->where('public_id', $meetingMinute)
            ->where(fn (Builder $access) => $access
                ->where('creator_id', $request->user()->id)
                ->orWhereHas('scheduleEvent.attendees', fn (Builder $attendees) => $attendees->where('users.id', $request->user()->id)))
            ->with(['creator', 'project', 'scheduleEvent', 'items.pic', 'files.uploader'])
            ->firstOrFail();

        return view('desk.schedule.mom.show', ['requesterWorkspace' => $workspace, 'meetingMinute' => $minute]);
    }

    public function summary(
        DraftRequesterMeetingSummaryRequest $request,
        Workspace $workspace,
        string $event,
        DepartmentWorkspaceService $workspaces,
        OpenAiMeetingSummary $draft,
    ): JsonResponse {
        try {
            $summary = $draft->generate($workspaces->deliveryWorkspace(), $request->user(), $request->string('title')->toString(), $request->validated('items'));
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'AI could not summarize the meeting right now. Please try again.'], 503);
        }

        return response()->json(['message' => 'Meeting summary generated.', 'data' => ['summary' => $summary]]);
    }

    public function download(Request $request, Workspace $workspace, string $meetingMinute, string $file, DepartmentWorkspaceService $workspaces): StreamedResponse
    {
        $this->authorizeRequesterWorkspace($workspace);
        $minute = MeetingMinute::query()
            ->where('workspace_id', $workspaces->deliveryWorkspace()->id)
            ->where('public_id', $meetingMinute)
            ->where(fn (Builder $access) => $access
                ->where('creator_id', $request->user()->id)
                ->orWhereHas('scheduleEvent.attendees', fn (Builder $attendees) => $attendees->where('users.id', $request->user()->id)))
            ->firstOrFail();
        $document = ProjectFile::query()->where('public_id', $file)
            ->where('attachable_type', $minute->getMorphClass())->where('attachable_id', $minute->id)->firstOrFail();
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name, ['Content-Type' => $document->mime_type]);
    }

    private function event(Request $request, Workspace $workspace, string $publicId): ScheduleEvent
    {
        return ScheduleEvent::query()
            ->where('workspace_id', $workspace->id)
            ->where('public_id', $publicId)
            ->where(fn (Builder $access) => $access
                ->where('creator_id', $request->user()->id)
                ->orWhereHas('attendees', fn (Builder $attendees) => $attendees->where('users.id', $request->user()->id)))
            ->firstOrFail();
    }

    private function authorizeRequesterWorkspace(Workspace $workspace): void
    {
        abort_unless($workspace->memberships()->active()->where('user_id', request()->user()->id)
            ->where('role', WorkspaceRole::REQUESTER->value)->exists(), 403);
    }
}
