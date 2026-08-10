<?php

namespace App\Http\Controllers\Desk;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Services\DepartmentWorkspaceService;
use App\Services\RequesterItTimeline;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItTimelineController extends Controller
{
    public function index(
        Request $request,
        DepartmentWorkspaceService $departmentWorkspaces,
        RequesterItTimeline $timeline,
    ): View {
        abort_unless($request->user()->workspaceMemberships()
            ->active()
            ->where('role', WorkspaceRole::REQUESTER->value)
            ->exists(), 403);

        $deliveryWorkspace = $departmentWorkspaces->deliveryWorkspace();

        return view('desk.it-timeline', [
            'deliveryWorkspace' => $deliveryWorkspace,
            ...$timeline->build($deliveryWorkspace, $request->string('scale')->toString()),
        ]);
    }
}
