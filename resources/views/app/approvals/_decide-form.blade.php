{{-- One decision form, used inline in the queue and on the detail page. Two copies would
     drift, and the one that drifts is the one nobody is looking at.
     $compact is for the 360px rail: three side-by-side cards there wrap to four lines each,
     and no media query helps because the rail is that narrow at every screen size. --}}
@php($compact = $compact ?? false)

<form method="POST" action="{{ route('app.approvals.decide', [$workspace, $request]) }}" class="space-y-4" x-data="{ decision: 'approved' }">
    @csrf
    <x-form-errors :except="['estimated_hours', 'decision_note', 'decision']" />

    <div @class(['grid gap-2', 'sm:grid-cols-3' => ! $compact])>
        @foreach ([['approved', 'Approve', 'It goes to scheduling.'], ['needs_info', 'Ask for more detail', 'Back to the requester, stays open.'], ['rejected', 'Reject', 'Closed, with your reason.']] as [$value, $label, $hint])
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                <input type="radio" name="decision" value="{{ $value }}" x-model="decision" @checked($value === 'approved') class="mt-1 border-slate-300 text-orbit-600 focus:ring-orbit-500">
                <span class="min-w-0"><span class="block text-sm font-semibold">{{ $label }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $hint }}</span></span>
            </label>
        @endforeach
    </div>

    <div @class(['grid gap-4', 'sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end' => ! $compact])>
        <div>
            <div x-show="decision === 'approved'" x-cloak>
                <x-label :for="'assignee_public_id_'.$request->public_id">IT PIC</x-label>
                <x-select :id="'assignee_public_id_'.$request->public_id" name="assignee_public_id" x-bind:required="decision === 'approved'">
                    <option value="">Choose an IT PIC</option>
                    @foreach ($picCandidates as $candidate)
                        <option value="{{ $candidate->user->public_id }}" @selected(old('assignee_public_id', $request->assignee?->public_id ?? $request->system->pic()?->public_id) === $candidate->user->public_id)>
                            {{ $candidate->user->name }} · {{ $candidate->role->label() }}
                        </option>
                    @endforeach
                </x-select>
                <x-field-error name="assignee_public_id" />
                <p class="mt-2 text-xs text-slate-500">This person owns the request and its proposed task plan. The scheduler uses their real capacity.</p>

                <div class="mt-4">
                <x-label :for="'estimated_hours_'.$request->public_id">How many working hours will this take?</x-label>
                <x-input :id="'estimated_hours_'.$request->public_id" type="number" step="0.5" min="0.5" name="estimated_hours" value="{{ old('estimated_hours') }}" placeholder="e.g. 16" />
                <x-field-error name="estimated_hours" />
                <p class="mt-2 text-xs text-slate-500">Total hours of work, not a deadline. Orbitra picks the start and due dates from this and the assignee's free capacity — a rough number is fine, it can be corrected later.</p>
                </div>

                <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <div @class(['grid gap-3', 'sm:grid-cols-2' => ! $compact])>
                        <div>
                            <x-label :for="'scheduled_start_'.$request->public_id">Start date (optional)</x-label>
                            <x-input :id="'scheduled_start_'.$request->public_id" type="date" name="scheduled_start" value="{{ old('scheduled_start') }}" />
                            <x-field-error name="scheduled_start" />
                        </div>
                        <div>
                            <x-label :for="'scheduled_due_'.$request->public_id">Due date (optional)</x-label>
                            <x-input :id="'scheduled_due_'.$request->public_id" type="date" name="scheduled_due" value="{{ old('scheduled_due') }}" />
                            <x-field-error name="scheduled_due" />
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Leave both empty and the planner books the earliest slot with real capacity. Fill them in to pin the dates yourself — the planner then leaves this request alone.</p>
                </div>
            </div>

            <div x-show="decision !== 'approved'" x-cloak>
                <x-label :for="'decision_note_'.$request->public_id">Why?</x-label>
                <x-textarea :id="'decision_note_'.$request->public_id" name="decision_note" rows="3" placeholder="What is missing, or why this will not go ahead.">{{ old('decision_note') }}</x-textarea>
                <x-field-error name="decision_note" />
            </div>
        </div>

        <x-button @class(['w-full' => $compact])>Record decision</x-button>
    </div>
</form>
