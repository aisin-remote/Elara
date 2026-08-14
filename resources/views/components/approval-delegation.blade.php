<details class="mt-6 rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-5 font-bold">
        <span>Approval delegation</span><span class="text-xs font-normal text-slate-500">{{ $delegations->count() }} active or upcoming</span>
    </summary>
    <div class="grid gap-6 border-t border-slate-200 p-5 lg:grid-cols-2 dark:border-slate-800">
        <div>
            <h3 class="text-sm font-bold">Delegate my approvals</h3>
            <form method="POST" action="{{ route('internal.approval-delegations.store', $workspace) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                @csrf
                <div class="sm:col-span-2"><x-label for="delegate_public_id">Backup approver</x-label><x-select id="delegate_public_id" name="delegate_public_id" required><option value="">Choose member…</option>@foreach($delegationMembers as $membership)<option value="{{ $membership->user->public_id }}">{{ $membership->user->name }}</option>@endforeach</x-select></div>
                <div class="sm:col-span-2"><x-label for="delegation_scope">Scope</x-label><x-select id="delegation_scope" name="scope">@foreach($delegationScopes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-select></div>
                <div><x-label for="delegation_start">Starts</x-label><x-input id="delegation_start" type="datetime-local" name="starts_at" value="{{ now()->format('Y-m-d\TH:i') }}" required /></div>
                <div><x-label for="delegation_end">Ends</x-label><x-input id="delegation_end" type="datetime-local" name="ends_at" value="{{ now()->addWeek()->format('Y-m-d\TH:i') }}" required /></div>
                <x-button class="sm:col-span-2">Save delegate</x-button>
            </form>
        </div>
        <div>
            <h3 class="text-sm font-bold">Current delegation</h3>
            <div class="mt-3 space-y-2">
                @forelse($delegations as $delegation)
                    <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60"><x-avatar :name="$delegation->delegate->name" size="size-9" /><span class="min-w-0 flex-1 text-sm"><strong class="block truncate">{{ $delegation->delegate->name }}</strong><span class="text-xs text-slate-500">{{ ucfirst($delegation->scope) }} · until {{ $delegation->ends_at->format('M j, H:i') }}</span></span><form method="POST" action="{{ route('internal.approval-delegations.destroy', $delegation) }}">@csrf @method('DELETE')<button class="text-xs font-bold text-rose-600">Remove</button></form></div>
                @empty <p class="text-sm text-slate-500">No backup approver set.</p> @endforelse
            </div>
            @if($incomingDelegations->isNotEmpty())<h3 class="mt-5 text-sm font-bold">Delegated to me</h3><div class="mt-2 space-y-1 text-sm text-slate-500">@foreach($incomingDelegations as $incoming)<p>{{ $incoming->delegator->name }} · {{ ucfirst($incoming->scope) }} until {{ $incoming->ends_at->format('M j') }}</p>@endforeach</div>@endif
        </div>
    </div>
</details>
