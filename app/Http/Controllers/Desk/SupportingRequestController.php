<?php

namespace App\Http\Controllers\Desk;

use App\Enums\SupportingTaskCategory;
use App\Enums\SupportingTaskStatus;
use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\SupportingTask;
use App\Models\Workspace;
use App\Services\DepartmentWorkspaceService;
use App\Services\OrganizationDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportingRequestController extends Controller
{
    public function create(Workspace $workspace): View
    {
        $this->authorize('create', [FeatureRequest::class, $workspace]);

        return view('desk.supporting.create', [
            'workspace' => $workspace,
            'categories' => SupportingTaskCategory::cases(),
            'priorities' => TaskPriority::cases(),
        ]);
    }

    public function store(Request $request, Workspace $workspace, OrganizationDirectory $organization, DepartmentWorkspaceService $workspaces): RedirectResponse
    {
        $this->authorize('create', [FeatureRequest::class, $workspace]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'category' => ['required', Rule::enum(SupportingTaskCategory::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $profile = $organization->requireProfile($request->user());
        $deliveryWorkspace = config('organization.jit_auth') ? $workspaces->deliveryWorkspace() : $workspace;

        $task = DB::transaction(function () use ($request, $data, $profile, $deliveryWorkspace): SupportingTask {
            $task = SupportingTask::create([
                'workspace_id' => $deliveryWorkspace->id,
                'creator_id' => $request->user()->id,
                'requester_department_id' => $profile['department_id'],
                'requester_department_code' => $profile['department_code'],
                'requester_department_name' => $profile['department_name'],
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $data['category'],
                'priority' => $data['priority'],
                'status' => SupportingTaskStatus::TODO,
                'due_date' => $data['needed_by'] ?? null,
            ]);
            ActivityLog::record($deliveryWorkspace, $task, 'supporting.request_created', $request->user(), ipAddress: $request->ip());

            return $task;
        });

        return redirect()->route('desk.supporting.show', $task)->with('status', 'Support request sent to ITD.');
    }

    public function show(SupportingTask $supportingTask): View
    {
        $this->authorize('view', $supportingTask);

        return view('desk.supporting.show', ['task' => $supportingTask->load(['creator', 'assignee'])]);
    }
}
