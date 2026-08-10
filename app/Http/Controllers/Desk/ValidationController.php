<?php

namespace App\Http\Controllers\Desk;

use App\Actions\Validation\RespondToCheckpoint;
use App\Enums\CheckpointStatus;
use App\Http\Controllers\Controller;
use App\Models\ValidationCheckpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ValidationController extends Controller
{
    public function index(Request $request): View
    {
        $checkpoints = ValidationCheckpoint::query()
            ->visibleTo($request->user())
            ->with(['task', 'subject'])
            ->orderByRaw("case when status = 'open' then 0 else 1 end")
            ->orderBy('expires_at')
            ->get();

        return view('desk.validations.index', [
            'open' => $checkpoints->where('status', CheckpointStatus::OPEN),
            'answered' => $checkpoints->where('status', '!=', CheckpointStatus::OPEN),
        ]);
    }

    public function respond(Request $request, ValidationCheckpoint $checkpoint, RespondToCheckpoint $action): RedirectResponse
    {
        $this->authorize('respond', $checkpoint);

        $data = $request->validate([
            'decision' => ['required', 'in:approved,changes_requested'],
            'response_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->handle(
            $checkpoint,
            $request->user(),
            CheckpointStatus::from($data['decision']),
            $data['response_note'] ?? null,
        );

        return redirect()
            ->route('desk.validations.index')
            ->with('status', $data['decision'] === 'approved'
                ? 'Confirmed. The team carries on.'
                : 'Sent back to the team with your note.');
    }
}
