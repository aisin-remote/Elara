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
                <x-label :for="'estimated_hours_'.$request->public_id">Rough effort, in hours</x-label>
                <x-input :id="'estimated_hours_'.$request->public_id" type="number" step="0.5" min="0.5" name="estimated_hours" value="{{ old('estimated_hours') }}" placeholder="16" />
                <x-field-error name="estimated_hours" />
                <p class="mt-2 text-xs text-slate-500">The scheduler needs a number to find a real slot. A rough one is fine — it can be corrected later.</p>
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
