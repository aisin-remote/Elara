<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ScheduleEvent;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Request $request, Workspace $workspace): View
    {
        $this->authorize('viewAny', [ScheduleEvent::class, $workspace]);

        return view('app.schedule.index', [
            'workspace' => $workspace,
            'calendarUrl' => route('internal.calendar.index', $workspace),
            'projects' => $workspace->projects()->visibleTo($request->user())->orderBy('name')->get(),
            'members' => $workspace->memberships()->active()->with('user')->orderBy('id')->get(),
        ]);
    }
}
