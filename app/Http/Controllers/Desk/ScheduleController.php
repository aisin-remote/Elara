<?php

namespace App\Http\Controllers\Desk;

use App\Actions\Schedule\CreateScheduleEvent;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\StoreRequesterScheduleEventRequest;
use App\Models\ScheduleEvent;
use App\Models\Workspace;
use App\Services\DepartmentWorkspaceService;
use App\Services\NotificationPreferenceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Workspace $workspace, DepartmentWorkspaceService $workspaces): View
    {
        $this->authorizeRequesterWorkspace($workspace);
        $deliveryWorkspace = $workspaces->deliveryWorkspace();

        return view('desk.schedule.index', [
            'workspace' => $workspace,
            'deliveryWorkspace' => $deliveryWorkspace,
            'calendarUrl' => route('desk.schedule.events', $workspace),
            'members' => $deliveryWorkspace->memberships()->active()
                ->where('role', '!=', WorkspaceRole::REQUESTER->value)
                ->whereHas('user')
                ->with('user')
                ->get()
                ->sortBy(fn ($membership) => strtolower($membership->user->name))
                ->values(),
        ]);
    }

    public function events(Request $request, Workspace $workspace, DepartmentWorkspaceService $workspaces): JsonResponse
    {
        $this->authorizeRequesterWorkspace($workspace);
        $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        $deliveryWorkspace = $workspaces->deliveryWorkspace();
        $start = Carbon::parse($request->string('start')->toString(), $deliveryWorkspace->timezone)->utc();
        $end = Carbon::parse($request->string('end')->toString(), $deliveryWorkspace->timezone)->utc();

        $events = ScheduleEvent::query()
            ->where('workspace_id', $deliveryWorkspace->id)
            ->where(fn (Builder $access) => $access
                ->where('creator_id', $request->user()->id)
                ->orWhereHas('attendees', fn (Builder $attendees) => $attendees->where('users.id', $request->user()->id)))
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->with('meetingMinute')
            ->orderBy('start_at')
            ->get()
            ->map(fn (ScheduleEvent $event) => [
                'id' => 'event-'.$event->public_id,
                'type' => 'event',
                'title' => $event->title,
                'start' => $event->start_at->toIso8601String(),
                'end' => $event->end_at->toIso8601String(),
                'color' => $event->color ?? '#0ea5e9',
                'meeting_url' => $event->meeting_url,
                'description' => $event->description,
                'mom_url' => $event->meetingMinute
                    ? route('desk.schedule.mom.show', [$workspace, $event->meetingMinute->public_id])
                    : route('desk.schedule.mom.create', [$workspace, $event->public_id]),
                'mom_label' => $event->meetingMinute ? 'Open MOM' : 'Create MOM',
                'can_update' => false,
                'mutation' => [],
            ]);

        return response()->json([
            'data' => $events,
            'meta' => ['timezone' => $deliveryWorkspace->timezone],
        ]);
    }

    public function store(
        StoreRequesterScheduleEventRequest $request,
        Workspace $workspace,
        DepartmentWorkspaceService $workspaces,
        CreateScheduleEvent $create,
        NotificationPreferenceService $notifications,
    ): RedirectResponse {
        $deliveryWorkspace = $workspaces->deliveryWorkspace();
        $result = $create->handle($deliveryWorkspace, $request->user(), [
            ...$request->validated(),
            'project_public_id' => null,
            'color' => '#0ea5e9',
            'additional_attendee_ids' => [$request->user()->id],
        ], $request->ip());

        $event = $result['event'];
        $when = $event->start_at->timezone($deliveryWorkspace->timezone)->format('M j, Y H:i');

        foreach ($event->attendees->where('id', '!=', $request->user()->id) as $attendee) {
            $notifications->notify(
                $attendee,
                $deliveryWorkspace,
                'team_activity',
                'Meeting invitation from '.$request->user()->name,
                $event->title.' · '.$when,
                route('app.schedule.index', $deliveryWorkspace),
                ['schedule_event_public_id' => $event->public_id],
            );
        }

        $conflicts = $result['conflicts']->pluck('title')->all();
        $message = $conflicts === []
            ? 'Meeting invitation sent to the IT team.'
            : 'Meeting invitation sent. Schedule conflict: '.implode(', ', $conflicts).'.';

        return redirect()->route('desk.schedule.index', $workspace)->with('status', $message);
    }

    private function authorizeRequesterWorkspace(Workspace $workspace): void
    {
        abort_unless($workspace->memberships()->active()
            ->where('user_id', request()->user()->id)
            ->where('role', WorkspaceRole::REQUESTER->value)
            ->exists(), 403);
    }
}
