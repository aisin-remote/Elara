<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Ai\AcceptTaskBreakdown;
use App\Enums\BreakdownStatus;
use App\Http\Requests\Ai\AcceptTaskBreakdownRequest;
use App\Jobs\GenerateTaskBreakdown;
use App\Models\ActivityLog;
use App\Models\TaskBreakdown;
use App\Services\CapacityPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskBreakdownController extends Controller
{
    public function accept(AcceptTaskBreakdownRequest $request, TaskBreakdown $breakdown, AcceptTaskBreakdown $action): JsonResponse|RedirectResponse
    {
        $accepted = $action->handle($breakdown, $request->user(), $request->tasks());

        return $this->success(
            $request,
            ['public_id' => $accepted->public_id, 'tasks' => count($accepted->tasks())],
            'Plan accepted. The tasks are on the board.',
            back()->getTargetUrl(),
        );
    }

    public function regenerate(Request $request, TaskBreakdown $breakdown): JsonResponse|RedirectResponse
    {
        $this->authorize('manage', $breakdown);
        abort_if($breakdown->status === BreakdownStatus::ACCEPTED, 422);

        $note = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ])['note'] ?? null;

        // Back to pending so the job picks this row up instead of adding another.
        $breakdown->update(['status' => BreakdownStatus::PENDING, 'error_message' => null]);
        GenerateTaskBreakdown::dispatch($breakdown->subject, $note);

        ActivityLog::record($breakdown->workspace, $breakdown->subject, 'task_breakdown.regenerating', $request->user(), [
            'note' => $note,
        ]);

        return $this->success($request, ['public_id' => $breakdown->public_id], 'Asking for a new plan.', back()->getTargetUrl());
    }

    public function discard(Request $request, TaskBreakdown $breakdown): JsonResponse|RedirectResponse
    {
        $this->authorize('manage', $breakdown);
        abort_if($breakdown->status === BreakdownStatus::ACCEPTED, 422);

        $breakdown->update(['status' => BreakdownStatus::DISCARDED]);

        ActivityLog::record($breakdown->workspace, $breakdown->subject, 'task_breakdown.discarded', $request->user());

        return $this->success($request, ['public_id' => $breakdown->public_id], 'Draft discarded. Enter the tasks yourself.', back()->getTargetUrl());
    }

    /**
     * What the edited estimates mean for the delivery date. Answered by the planner rather
     * than by arithmetic in the browser: weekends, holidays, and leave are its job, and a
     * second implementation of them would drift from the first.
     */
    public function preview(Request $request, TaskBreakdown $breakdown, CapacityPlanner $planner): JsonResponse
    {
        $this->authorize('manage', $breakdown);

        $data = $request->validate([
            'minutes' => ['required', 'array', 'min:1', 'max:60'],
            'minutes.*' => ['required', 'integer', 'min:1', 'max:4800'],
        ]);

        $subject = $breakdown->subject;
        $assignee = $subject->assignee;
        $total = array_sum($data['minutes']);

        if (! $assignee) {
            return response()->json(['total_minutes' => $total, 'finish' => null, 'assignee' => null]);
        }

        $start = $subject->scheduled_start
            ? CarbonImmutable::parse($subject->scheduled_start)
            : CarbonImmutable::now($breakdown->workspace->timezone ?: 'UTC');

        // Ignoring this request's own reservation: it is exactly what these tasks replace,
        // and counting it would push every preview a window later than reality.
        $dates = $planner->layOut($breakdown->workspace, $assignee, $data['minutes'], $start, $subject);
        $finish = end($dates);

        return response()->json([
            'total_minutes' => $total,
            'finish' => $finish->format('Y-m-d'),
            'finish_label' => $finish->format('D, M j, Y'),
            'assignee' => $assignee->name,
        ]);
    }
}
