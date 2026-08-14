<?php

namespace App\Http\Controllers\InternalApi;

use App\Models\ApprovalDelegation;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApprovalDelegationController extends Controller
{
    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($workspace->memberships()->active()->where('user_id', $request->user()->id)->exists(), 403);
        $data = $request->validate([
            'delegate_public_id' => ['required', 'string', 'size:26'],
            'scope' => ['required', Rule::in(['all', 'feature', 'project', 'department'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at', 'before_or_equal:'.now()->addDays(90)->toDateTimeString()],
        ]);
        $delegateId = $workspace->memberships()->active()
            ->where('user_id', '!=', $request->user()->id)
            ->whereHas('user', fn ($query) => $query->where('public_id', $data['delegate_public_id']))
            ->value('user_id');
        abort_unless($delegateId, 422, 'Choose an active workspace member.');

        ApprovalDelegation::create([
            'workspace_id' => $workspace->id,
            'delegator_id' => $request->user()->id,
            'delegate_id' => $delegateId,
            'scope' => $data['scope'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
        ]);

        return back()->with('success', 'Approval delegate saved.');
    }

    public function destroy(Request $request, ApprovalDelegation $approvalDelegation): RedirectResponse
    {
        abort_unless($approvalDelegation->delegator_id === $request->user()->id, 403);
        $approvalDelegation->delete();

        return back()->with('success', 'Approval delegation removed.');
    }
}
