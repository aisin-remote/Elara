<?php

namespace App\Http\Controllers\App;

use App\Enums\MeetingMinuteStatus;
use App\Enums\ProjectType;
use App\Http\Controllers\Controller;
use App\Models\MeetingMinute;
use App\Models\ScheduleEvent;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingMinuteController extends Controller
{
    public function index(Request $request, Workspace $workspace): View
    {
        $this->authorize('viewAny', [MeetingMinute::class, $workspace]);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'lifecycle' => ['nullable', 'in:draft,published,locked'],
            'project' => ['nullable', 'string', 'size:26'], 'pic' => ['nullable', 'string', 'size:26'],
            'action_status' => ['nullable', 'in:outstanding,in_progress,pending,done'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $minutes = $workspace->meetingMinutes()
            ->visibleTo($request->user())
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($search) => $search->where('title', 'like', '%'.$q.'%')->orWhere('summary', 'like', '%'.$q.'%')))
            ->when($filters['lifecycle'] ?? null, fn ($query, $status) => $query->where('publication_status', $status))
            ->when($filters['project'] ?? null, fn ($query, $project) => $query->whereHas('project', fn ($related) => $related->where('public_id', $project)))
            ->when($filters['pic'] ?? null, fn ($query, $pic) => $query->whereHas('items.pic', fn ($related) => $related->where('public_id', $pic)))
            ->when($filters['action_status'] ?? null, fn ($query, $status) => $query->whereHas('items', fn ($items) => $items->where('status', $status)))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('meeting_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('meeting_at', '<=', $to))
            ->with(['creator', 'project'])
            ->withCount([
                'items',
                'items as done_items_count' => fn ($query) => $query->where('status', MeetingMinuteStatus::DONE->value),
                'items as tba_items_count' => fn ($query) => $query->whereNull('due_date'),
                'files',
            ])
            ->latest('meeting_at')
            ->paginate(12)
            ->withQueryString();

        return view('app.schedule.minutes.index', [
            'workspace' => $workspace, 'minutes' => $minutes, 'filters' => $filters,
            'projects' => $workspace->projects()->whereNull('archived_at')->orderBy('name')->get(['public_id', 'name']),
            'picUsers' => $workspace->memberships()->active()->with('user')->get()->pluck('user')->filter()->sortBy('name'),
        ]);
    }

    public function create(Request $request, Workspace $workspace): View|RedirectResponse
    {
        $this->authorize('create', [MeetingMinute::class, $workspace]);
        $scheduleEvent = $request->filled('event')
            ? ScheduleEvent::query()
                ->visibleTo($request->user())
                ->where('workspace_id', $workspace->id)
                ->where('public_id', $request->string('event'))
                ->with(['meetingMinute', 'attendees'])
                ->firstOrFail()
            : null;

        if ($scheduleEvent?->meetingMinute) {
            return redirect()->route('app.schedule.minutes.show', [$workspace, $scheduleEvent->meetingMinute]);
        }

        return view('app.schedule.minutes.create', $this->formData($request, $workspace, scheduleEvent: $scheduleEvent));
    }

    public function show(Workspace $workspace, MeetingMinute $meetingMinute): View
    {
        abort_unless($meetingMinute->workspace_id === $workspace->id, 404);
        $this->authorize('view', $meetingMinute);

        $meetingMinute->load(['creator', 'project', 'scheduleEvent', 'items.pic', 'files.uploader', 'revisions.editor']);

        return view('app.schedule.minutes.show', compact('workspace', 'meetingMinute'));
    }

    public function edit(Request $request, Workspace $workspace, MeetingMinute $meetingMinute): View
    {
        abort_unless($meetingMinute->workspace_id === $workspace->id, 404);
        $this->authorize('update', $meetingMinute);
        $meetingMinute->load(['scheduleEvent.attendees', 'items.pic', 'files.uploader']);

        return view('app.schedule.minutes.edit', $this->formData($request, $workspace, $meetingMinute, $meetingMinute->scheduleEvent));
    }

    private function formData(Request $request, Workspace $workspace, ?MeetingMinute $meetingMinute = null, ?ScheduleEvent $scheduleEvent = null): array
    {
        $projects = $workspace->projects()
            ->where('type', '!=', ProjectType::PERSONAL->value)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'type']);
        $picUsers = $workspace->memberships()->active()
            ->whereHas('user')
            ->with('user')
            ->get()
            ->pluck('user')
            ->merge($scheduleEvent?->attendees ?? collect())
            ->unique('id')
            ->sortBy(fn ($user) => strtolower($user->name))
            ->values();

        $items = old('items');
        if (! is_array($items)) {
            $items = $meetingMinute
                ? $meetingMinute->items->map(fn ($item) => [
                    'content' => $item->content,
                    'pic_name' => $item->pic_name,
                    'pic_user_public_id' => $item->pic?->public_id,
                    'due_date' => $item->due_date?->format('Y-m-d'),
                    'status' => $item->status->value,
                ])->values()->all()
                : [[
                    'content' => '',
                    'pic_name' => '',
                    'pic_user_public_id' => null,
                    'due_date' => null,
                    'status' => MeetingMinuteStatus::OUTSTANDING->value,
                ]];
        }

        return [
            'workspace' => $workspace,
            'meetingMinute' => $meetingMinute,
            'scheduleEvent' => $scheduleEvent,
            'projects' => $projects,
            'picUsers' => $picUsers,
            'statuses' => MeetingMinuteStatus::cases(),
            'formItems' => array_values($items),
            'aiSummaryUrl' => route('internal.ai.meeting-summary', $workspace),
        ];
    }
}
