<?php

namespace App\Http\Controllers\App;

use App\Actions\Request\TransitionFeatureRequest;
use App\Enums\BreakdownStatus;
use App\Enums\FeatureRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Request\DecideFeatureRequestRequest;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Models\TaskBreakdown;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request, Workspace $workspace): View
    {
        $this->authorize('viewAny', [FeatureRequest::class, $workspace]);

        $pending = FeatureRequest::where('workspace_id', $workspace->id)
            ->awaitingReview()
            ->with(['system', 'requester'])
            ->get()
            // High urgency sorts first, then oldest: a queue that ignores age grows a tail.
            ->sortBy([fn ($a, $b) => $b->urgency->weight() <=> $a->urgency->weight(), fn ($a, $b) => $a->created_at <=> $b->created_at])
            ->values();

        $awaitingAcceptance = TaskBreakdown::where('workspace_id', $workspace->id)
            ->where('status', BreakdownStatus::READY->value)
            ->with('subject')
            ->oldest('generated_at')
            ->get();

        $pendingProjects = ProjectRequest::where('workspace_id', $workspace->id)
            ->awaitingDecision()
            ->with(['requester', 'supervisor'])
            ->oldest('created_at')
            ->get();

        $counts = [
            'feature' => $pending->count(),
            'project' => $pendingProjects->count(),
            'plans' => $awaitingAcceptance->count(),
        ];

        return view('app.approvals.index', [
            'workspace' => $workspace,
            'pending' => $pending,
            'pendingProjects' => $pendingProjects,
            'awaitingAcceptance' => $awaitingAcceptance,
            'counts' => $counts,
            // Landing on an empty tab while another one has work is the page failing at its
            // only job, so the default follows the work rather than a fixed order.
            'tab' => $this->activeTab($request->string('tab')->toString(), $counts),
            'recent' => FeatureRequest::where('workspace_id', $workspace->id)
                ->whereNotNull('reviewed_at')
                ->with(['system', 'requester', 'reviewer'])
                ->latest('reviewed_at')
                ->limit(10)
                ->get(),
        ]);
    }

    /** @param  array<string, int>  $counts */
    private function activeTab(string $requested, array $counts): string
    {
        if (array_key_exists($requested, $counts) || $requested === 'decided') {
            return $requested;
        }

        foreach ($counts as $tab => $count) {
            if ($count > 0) {
                return $tab;
            }
        }

        return 'feature';
    }

    public function show(Request $request, Workspace $workspace, FeatureRequest $featureRequest): View
    {
        abort_unless($featureRequest->workspace_id === $workspace->id, 404);
        $this->authorize('view', $featureRequest);

        return view('app.approvals.show', [
            'workspace' => $workspace,
            'request' => $featureRequest->load(['system.members', 'requester', 'reviewer']),
            'canDecide' => $request->user()->can('decide', $featureRequest),
            'breakdown' => $featureRequest->breakdowns()->with('acceptedBy')->latest('id')->first(),
            'timeline' => ActivityLog::where('subject_type', $featureRequest->getMorphClass())
                ->where('subject_id', $featureRequest->id)
                ->with('actor')
                ->latest('created_at')
                ->get(),
        ]);
    }

    public function decide(DecideFeatureRequestRequest $request, Workspace $workspace, FeatureRequest $featureRequest, TransitionFeatureRequest $transition): RedirectResponse
    {
        abort_unless($featureRequest->workspace_id === $workspace->id, 404);

        $decision = FeatureRequestStatus::from($request->string('decision')->toString());

        // Approving without an effort figure would put the request in a queue the planner
        // can never drain, so the estimate is collected with the decision. PRD-06 replaces
        // this hand-typed number with an AI breakdown.
        if ($decision === FeatureRequestStatus::APPROVED) {
            $hours = $request->validate([
                'estimated_hours' => ['required', 'numeric', 'min:0.5', 'max:400'],
            ])['estimated_hours'];

            $featureRequest->update(['estimated_minutes' => (int) round($hours * 60)]);
        }

        $transition->handle($featureRequest, $decision, $request->user(), $request->input('decision_note'));

        return redirect()
            ->route('app.approvals.index', $workspace)
            ->with('status', match ($decision) {
                FeatureRequestStatus::APPROVED => 'Approved. It joins the scheduling queue.',
                FeatureRequestStatus::REJECTED => 'Rejected, and the requester has been told why.',
                default => 'Sent back to the requester for more detail.',
            });
    }
}
