@php
    $canManage = $breakdown && auth()->user()->can('manage', $breakdown);
    $draft = $breakdown?->tasks() ?? [];
    $requesterOwned = $breakdown?->subject instanceof \App\Models\FeatureRequest
        || $breakdown?->subject instanceof \App\Models\ProjectRequest;
@endphp

<section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="breakdown-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 id="breakdown-title" class="font-bold">Proposed tasks</h3>
            <p class="mt-1 text-xs text-slate-500">
                Drafted automatically. Nothing reaches the board until someone here accepts it.
            </p>
        </div>
        @if ($breakdown)
            <x-badge :variant="match ($breakdown->status->value) {
                'ready' => 'info',
                'accepted' => 'success',
                'failed' => 'danger',
                default => 'neutral',
            }">{{ $breakdown->status->label() }}</x-badge>
        @endif
    </div>

    @if (! $breakdown)
        <div class="mt-4">
            <x-empty-state icon="sparkles" title="No plan yet"
                description="A breakdown is requested automatically when a request is approved." />
        </div>
    @elseif ($breakdown->status->value === 'pending')
        <p class="mt-4 text-sm text-slate-500">Generating a plan. Refresh in a moment.</p>
    @elseif ($breakdown->status->value === 'failed')
        <x-alert variant="error" :dismissible="false" class="mt-4 max-w-none">
            {{ $breakdown->error_message ?: 'The provider did not return a usable plan.' }}
        </x-alert>
        <p class="mt-3 text-xs text-slate-500">Entering the tasks by hand is unaffected.</p>
    @elseif ($breakdown->status->value === 'discarded')
        <p class="mt-4 text-sm text-slate-500">This draft was discarded. The tasks are being entered by hand.</p>
    @elseif ($breakdown->status->value === 'accepted')
        <p class="mt-4 text-sm text-slate-500">
            Accepted{{ $breakdown->acceptedBy ? ' by '.$breakdown->acceptedBy->name : '' }} —
            {{ count($draft) }} {{ \Illuminate\Support\Str::plural('task', count($draft)) }},
            {{ round($breakdown->totalMinutes() / 60, 1) }} hours.
        </p>
    @else
        <div x-data="taskBreakdown({{ Illuminate\Support\Js::from([
            'tasks' => $draft,
            'previewUrl' => route('internal.breakdowns.preview', $breakdown),
        ]) }})" class="mt-4">
            <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1 rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-800/60">
                <p class="text-sm"><span class="font-bold" x-text="totalHours"></span> hours total</p>
                <p class="text-sm text-slate-500">
                    Finishes <span class="font-semibold" x-text="finishLabel">—</span>
                </p>
            </div>

            <form method="POST" action="{{ route('internal.breakdowns.accept', $breakdown) }}" class="mt-4 space-y-3">
                @csrf
                <x-form-errors />
                <template x-for="(task, index) in tasks" :key="index">
                    {{-- One row per task: number, then title and description taking the width,
                         then estimate and remove pinned right. Stacks on narrow screens. --}}
                    <div class="grid gap-x-4 gap-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800 lg:grid-cols-[2rem_minmax(0,1fr)_9rem_2.5rem] lg:items-start">
                        <span class="pt-2.5 text-sm font-bold text-slate-300 dark:text-slate-600" x-text="index + 1"></span>

                        <div class="min-w-0 space-y-2">
                            <input type="text" :name="`tasks[${index}][title]`" x-model="task.title" maxlength="200" required
                                class="w-full rounded-lg border-slate-200 bg-transparent px-3 py-2 text-sm font-semibold focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700"
                                aria-label="Task title">
                            <textarea :name="`tasks[${index}][description]`" x-model="task.description" rows="3" maxlength="5000"
                                class="w-full resize-y rounded-lg border-slate-200 bg-transparent px-3 py-2 text-sm leading-6 text-slate-600 focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:text-slate-300"
                                aria-label="Task description"></textarea>

                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-bold text-slate-700 dark:text-slate-200">To-do checklist</p>
                                        <p class="text-[11px] text-slate-400">Checking these items updates task progress.</p>
                                    </div>
                                    <button type="button" @click="addChecklist(index)" class="text-xs font-semibold text-orbit-700 dark:text-orbit-300">+ Add item</button>
                                </div>
                                <div class="mt-2 space-y-2">
                                    <template x-for="(item, checklistIndex) in task.checklist" :key="`${index}-${checklistIndex}`">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs tabular-nums text-slate-400" x-text="checklistIndex + 1"></span>
                                            <input type="text" :name="`tasks[${index}][checklist][${checklistIndex}]`" x-model="task.checklist[checklistIndex]" maxlength="200" required
                                                class="min-w-0 flex-1 rounded-lg border-slate-200 bg-white px-3 py-2 text-xs focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-900"
                                                aria-label="Checklist item">
                                            <button type="button" @click="removeChecklist(index, checklistIndex)" class="rounded p-1 text-slate-400 hover:text-rose-600" aria-label="Remove checklist item">
                                                <x-icon name="close" class="size-3.5" />
                                            </button>
                                        </div>
                                    </template>
                                    <p x-show="task.checklist.length === 0" class="text-xs text-slate-400">No checklist items. Progress will stay at 0% until the task is completed.</p>
                                </div>
                            </div>

                            @if ($requesterOwned)
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                    <label class="flex items-center gap-2 text-xs text-slate-500">
                                        <input type="checkbox" :name="`tasks[${index}][requires_user_validation]`" value="1"
                                            x-model="task.requires_user_validation"
                                            class="rounded border-slate-300 text-orbit-600 focus:ring-orbit-500">
                                        Requester must confirm this
                                    </label>
                                </div>
                                <p class="text-xs text-slate-400" x-show="task.requires_user_validation && task.validation_reason" x-text="task.validation_reason"></p>
                                <input type="hidden" :name="`tasks[${index}][validation_reason]`" :value="task.validation_reason || ''">
                            @endif
                        </div>

                        <label class="flex items-center gap-2 text-xs text-slate-500">
                            <input type="number" :name="`tasks[${index}][estimate_minutes]`" x-model.number="task.estimate_minutes"
                                min="15" max="4800" step="15" required @input="schedulePreview"
                                class="w-full rounded-lg border-slate-200 bg-transparent px-3 py-2 text-sm tabular-nums focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700"
                                aria-label="Estimate in minutes">
                            min
                        </label>

                        <button type="button" @click="remove(index)"
                            class="justify-self-start rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800 lg:justify-self-end"
                            aria-label="Remove this task">
                            <x-icon name="trash" class="size-4" />
                        </button>
                    </div>
                </template>

                @if ($canManage)
                    <div class="flex flex-wrap gap-2 pt-1">
                        <x-button type="submit" x-bind:disabled="tasks.length === 0">Accept plan</x-button>
                    </div>
                @endif
            </form>

            @if ($canManage)
                <div class="mt-4 grid gap-3 border-t border-slate-200 pt-4 dark:border-slate-800 sm:grid-cols-[1fr_auto_auto]">
                    <form method="POST" action="{{ route('internal.breakdowns.regenerate', $breakdown) }}" class="contents">
                        @csrf
                        <x-input name="note" placeholder="Optional: what to change, e.g. smaller tasks" maxlength="500" aria-label="Regeneration note" />
                        <x-button variant="secondary">Regenerate</x-button>
                    </form>
                    <form method="POST" action="{{ route('internal.breakdowns.discard', $breakdown) }}">
                        @csrf
                        <x-button variant="secondary">Discard</x-button>
                    </form>
                </div>
            @endif
        </div>
    @endif

    @if ($breakdown && $breakdown->status->value !== 'pending')
        <p class="mt-4 text-xs text-slate-400">
            {{ $breakdown->provider }} · {{ $breakdown->model }}
            @if ($breakdown->input_tokens)
                · {{ number_format($breakdown->input_tokens) }} in / {{ number_format($breakdown->output_tokens) }} out
            @endif
        </p>
    @endif
</section>
