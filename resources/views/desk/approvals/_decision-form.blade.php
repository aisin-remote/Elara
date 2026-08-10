<section class="mt-6 rounded-2xl border border-orbit-200 bg-orbit-50/50 p-5 dark:border-orbit-900 dark:bg-orbit-950/20">
    <div class="flex items-start gap-3">
        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-orbit-100 text-orbit-700 dark:bg-orbit-900 dark:text-orbit-200"><x-icon name="check" /></span>
        <div>
            <h2 class="font-bold">Keputusan department Anda diperlukan</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Pastikan kebutuhan dan manfaatnya layak diteruskan ke ITD.</p>
        </div>
    </div>

    <form method="POST" action="{{ $request instanceof App\Models\ProjectRequest
        ? route('desk.department-approvals.projects.decide', [$activeWorkspace ?? $request->workspace, $request])
        : route('desk.department-approvals.features.decide', [$activeWorkspace ?? $request->workspace, $request]) }}"
        class="mt-5 space-y-4" x-data="{ decision: 'approve' }">
        @csrf
        <x-form-errors :except="['decision', 'note']" />

        <div class="grid gap-2 sm:grid-cols-3">
            @foreach ([['approve', 'Setujui'], ['needs_info', 'Minta dilengkapi'], ['reject', 'Tolak']] as [$value, $label])
                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white p-3 text-sm font-semibold dark:border-slate-700 dark:bg-slate-900">
                    <input type="radio" name="decision" value="{{ $value }}" x-model="decision" class="border-slate-300 text-orbit-600 focus:ring-orbit-500">
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <div x-show="decision !== 'approve'" x-cloak>
            <x-label for="department_note">Alasan atau informasi yang diperlukan</x-label>
            <x-textarea id="department_note" name="note" rows="3" placeholder="Jelaskan agar requester tahu apa yang perlu diperbaiki.">{{ old('note') }}</x-textarea>
            <x-field-error name="note" />
        </div>

        <x-button>Catat keputusan</x-button>
    </form>
</section>
