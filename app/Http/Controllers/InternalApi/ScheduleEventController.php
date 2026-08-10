<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Schedule\CreateScheduleEvent;
use App\Actions\Schedule\UpdateScheduleEvent;
use App\Http\Requests\Schedule\DeleteScheduleEventRequest;
use App\Http\Requests\Schedule\StoreScheduleEventRequest;
use App\Http\Requests\Schedule\UpdateScheduleEventRequest;
use App\Http\Resources\ScheduleEventResource;
use App\Models\ScheduleEvent;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleEventController extends Controller
{
    public function store(StoreScheduleEventRequest $request, Workspace $workspace, CreateScheduleEvent $create): JsonResponse|RedirectResponse
    {
        $result = $create->handle($workspace, $request->user(), $request->validated(), $request->ip());

        return $this->success(
            $request,
            $this->payload($request, $result),
            $this->message('Event created.', $result['conflicts']->pluck('title')->all()),
            route('app.schedule.index', $workspace),
            201,
        );
    }

    public function update(UpdateScheduleEventRequest $request, ScheduleEvent $event, UpdateScheduleEvent $update): JsonResponse|RedirectResponse
    {
        $result = $update->handle($event, $request->user(), $request->safe()->except('version'), $request->integer('version'), $request->ip());

        if (! $result) {
            return $this->conflict($request, $request->route('event')->fresh()->version);
        }

        return $this->success(
            $request,
            $this->payload($request, $result),
            $this->message('Event updated.', $result['conflicts']->pluck('title')->all()),
            route('app.schedule.index', $event->workspace),
        );
    }

    public function destroy(DeleteScheduleEventRequest $request, ScheduleEvent $event): JsonResponse|RedirectResponse
    {
        $workspace = $event->workspace;
        $event->delete();

        return $this->success($request, null, 'Event deleted.', route('app.schedule.index', $workspace));
    }

    private function payload(Request $request, array $result): array
    {
        return [
            'event' => (new ScheduleEventResource($result['event']))->resolve($request),
            'conflicts' => $result['conflicts']->map(fn (ScheduleEvent $event) => [
                'public_id' => $event->public_id,
                'title' => $event->title,
                'start_at' => $event->start_at->toIso8601String(),
                'end_at' => $event->end_at->toIso8601String(),
            ])->values(),
        ];
    }

    private function message(string $success, array $conflicts): string
    {
        return $conflicts === [] ? $success : $success.' Conflict warning: '.implode(', ', $conflicts).'.';
    }

    private function conflict(Request $request, int $serverVersion): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'The event has changed.', 'server_version' => $serverVersion], 409);
        }

        return back()->withInput()->withErrors(['version' => 'This event changed in another session. Refresh and try again.']);
    }
}
