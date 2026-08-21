<?php

namespace App\Http\Controllers\Desk;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Services\DepartmentWorkspaceService;
use App\Services\MeetingMinuteActionItems;
use App\Services\RequesterItTimeline;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItTimelineController extends Controller
{
    public function index(
        Request $request,
        DepartmentWorkspaceService $departmentWorkspaces,
        RequesterItTimeline $timeline,
        MeetingMinuteActionItems $meetingMinuteActionItems,
    ): View {
        $requesterMembership = $request->user()->workspaceMemberships()
            ->active()
            ->where('role', WorkspaceRole::REQUESTER->value)
            ->with('workspace:id,public_id,organization_department_id')
            ->first();

        abort_unless($requesterMembership, 403);

        $departmentId = $requesterMembership->workspace?->organization_department_id;
        abort_if(config('organization.required') && ! $departmentId, 403);

        $deliveryWorkspace = $departmentWorkspaces->deliveryWorkspace();

        return view('desk.it-timeline', [
            'deliveryWorkspace' => $deliveryWorkspace,
            'requesterWorkspace' => $requesterMembership->workspace,
            'momActionItems' => $meetingMinuteActionItems->forUser($request->user(), requesterWorkspace: $requesterMembership->workspace),
            ...$timeline->build(
                $deliveryWorkspace,
                $departmentId,
                $request->string('scale')->toString(),
                $request->string('view')->toString(),
            ),
        ]);
    }
}
