<section
    x-data="requestMonitoring({{ Illuminate\Support\Js::from(['initial' => $monitoring, 'url' => $monitoringUrl]) }})"
    class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
    aria-labelledby="request-monitoring-title">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div>
            <div class="flex items-center gap-2">
                <h2 id="request-monitoring-title" class="font-bold">Request progress</h2>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                    <span class="size-1.5 animate-pulse rounded-full bg-emerald-500"></span>Live
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">Updates automatically every 10 seconds without exposing the IT board.</p>
        </div>
        <button type="button" @click="refresh" :disabled="syncing"
            class="inline-flex min-h-9 items-center gap-2 rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <x-icon name="refresh" class="size-3.5" />
            <span x-text="syncing ? 'Loading…' : 'Refresh'">Refresh</span>
        </button>
    </header>

    <div class="p-5">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(260px,0.65fr)]">
            <div class="rounded-2xl bg-slate-950 p-5 text-white dark:bg-slate-800">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Current stage</p>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-2xl font-bold" x-text="data.current_stage"></h3>
                    <a x-show="data.action" :href="data.action?.url" class="rounded-xl bg-white px-3 py-2 text-xs font-bold text-slate-950" x-text="data.action?.label"></a>
                </div>
                <p class="mt-2 text-sm leading-6 text-slate-300" x-text="data.description"></p>
                <div class="mt-5 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/15" role="progressbar" aria-label="Delivery progress"
                        :aria-valuenow="data.progress" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-orbit-400 transition-[width] duration-500" :style="`width: ${data.progress}%`"></div>
                    </div>
                    <span class="text-sm font-bold tabular-nums" x-text="`${data.progress}%`"></span>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Tasks completed</dt>
                    <dd class="mt-1 text-lg font-bold tabular-nums"><span x-text="data.tasks.completed"></span><span class="text-sm text-slate-400"> / </span><span x-text="data.tasks.total"></span></dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Blocked</dt>
                    <dd class="mt-1 text-lg font-bold tabular-nums" :class="data.tasks.blocked ? 'text-rose-600 dark:text-rose-300' : ''" x-text="data.tasks.blocked"></dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">PIC</dt>
                    <dd class="mt-1 truncate text-sm font-bold" x-text="data.assignee || 'Not assigned'"></dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Schedule</dt>
                    <dd class="mt-1 text-sm font-bold" x-text="data.schedule || 'Not scheduled'"></dd>
                </div>
            </dl>
        </div>

        <div class="scrollbar-none mt-6 overflow-x-auto pb-2">
            <ol class="grid min-w-max gap-3" :style="`grid-template-columns: repeat(${data.stages.length}, minmax(148px, 1fr))`" aria-label="Request stages">
                <template x-for="(stage, index) in data.stages" :key="stage.key">
                    <li class="relative rounded-xl border p-3 transition"
                        :class="{
                            'border-emerald-200 bg-emerald-50/70 dark:border-emerald-900 dark:bg-emerald-950/30': stage.state === 'completed',
                            'border-orbit-300 bg-orbit-50 ring-2 ring-orbit-100 dark:border-orbit-700 dark:bg-orbit-950/40 dark:ring-orbit-900': stage.state === 'current',
                            'border-amber-300 bg-amber-50 ring-2 ring-amber-100 dark:border-amber-800 dark:bg-amber-950/30 dark:ring-amber-900/60': stage.state === 'attention',
                            'border-rose-300 bg-rose-50 ring-2 ring-rose-100 dark:border-rose-800 dark:bg-rose-950/30 dark:ring-rose-900/60': stage.state === 'failed',
                            'border-slate-200 bg-slate-50/70 opacity-65 dark:border-slate-800 dark:bg-slate-800/40': stage.state === 'upcoming',
                        }">
                        <div class="flex items-center gap-2">
                            <span class="grid size-6 shrink-0 place-items-center rounded-full text-xs font-black"
                                :class="{
                                    'bg-emerald-500 text-white': stage.state === 'completed',
                                    'bg-orbit-600 text-white': stage.state === 'current',
                                    'bg-amber-500 text-white': stage.state === 'attention',
                                    'bg-rose-500 text-white': stage.state === 'failed',
                                    'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-300': stage.state === 'upcoming',
                                }"
                                x-text="stage.state === 'completed' ? '✓' : (stage.state === 'failed' ? '!' : index + 1)"></span>
                            <span class="text-xs font-bold" x-text="stage.label"></span>
                        </div>
                        <time x-show="stage.time_label" :datetime="stage.time" class="mt-2 block text-[11px] text-slate-500" x-text="stage.time_label"></time>
                        <span x-show="stage.is_current" class="mt-2 block text-[10px] font-bold uppercase tracking-wide text-orbit-700 dark:text-orbit-300">Current position</span>
                    </li>
                </template>
            </ol>
        </div>

        <section x-cloak x-show="data.task_timeline?.length" class="mt-6 border-t border-slate-100 pt-6 dark:border-slate-800" aria-labelledby="delivery-task-timeline-title">
            <div>
                <h3 id="delivery-task-timeline-title" class="font-bold">Delivery task timeline</h3>
                <p class="mt-1 text-xs text-slate-500">Task details appear here after IT starts the work. Meeting notes and shared documents stay attached to the relevant task.</p>
            </div>

            <ol class="relative mt-5 space-y-4 before:absolute before:bottom-5 before:left-[11px] before:top-5 before:w-px before:bg-slate-200 dark:before:bg-slate-700">
                <template x-for="task in data.task_timeline" :key="task.public_id">
                    <li class="relative pl-9">
                        <span class="absolute left-0 top-5 z-10 size-6 rounded-full border-4 border-white dark:border-slate-900" :style="{ backgroundColor: task.status.color || '#64748b' }"></span>
                        <article class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="font-bold" x-text="task.title"></h4>
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold dark:bg-slate-800" x-text="task.status.name"></span>
                                        <span x-show="task.blocked" class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">Blocked</span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">
                                        <span x-text="task.assignees.length ? task.assignees.join(', ') : 'Unassigned'"></span>
                                        <span x-show="task.start_label || task.due_label"> · </span>
                                        <span x-show="task.start_label" x-text="task.start_label"></span>
                                        <span x-show="task.start_label && task.due_label"> – </span>
                                        <span x-show="task.due_label" x-text="task.due_label"></span>
                                    </p>
                                </div>
                                <span class="text-sm font-bold tabular-nums" x-text="`${task.progress}%`"></span>
                            </div>

                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar" aria-label="Task progress" :aria-valuenow="task.progress" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full bg-orbit-500 transition-[width]" :style="`width: ${task.progress}%`"></div>
                            </div>

                            <div class="mt-4 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">
                                <div class="flex items-center gap-2 text-xs font-bold"><x-icon name="files" class="size-4 text-slate-400" />Shared documents</div>
                                <div x-show="task.attachments.length" class="mt-2 space-y-2">
                                    <template x-for="file in task.attachments" :key="file.url">
                                        <a :href="file.url" class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-xs hover:text-orbit-700 dark:bg-slate-900 dark:hover:text-orbit-300">
                                            <span class="min-w-0 truncate font-semibold" x-text="file.name"></span>
                                            <span class="shrink-0 text-slate-400" x-text="`${file.size_label} · ${file.uploader}`"></span>
                                        </a>
                                    </template>
                                </div>
                                <p x-show="! task.attachments.length" class="mt-2 text-xs text-slate-500">No shared documents yet.</p>

                                <form x-show="task.upload_url" :action="task.upload_url" method="POST" enctype="multipart/form-data" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                                    @csrf
                                    <input type="hidden" name="share_with_requester" value="1">
                                    <label class="min-w-0 flex-1">
                                        <span class="sr-only">Choose a meeting document for this task</span>
                                        <input type="file" name="attachment" required class="block w-full text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:font-semibold file:text-slate-700 dark:file:bg-slate-900 dark:file:text-slate-200">
                                    </label>
                                    <x-button variant="secondary" class="shrink-0">Upload document</x-button>
                                </form>
                            </div>
                        </article>
                    </li>
                </template>
            </ol>
        </section>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-400" aria-live="polite">
            <span x-text="data.updated_label ? `Last change ${data.updated_label}` : 'No new changes yet'"></span>
            <span x-show="error" class="font-semibold text-amber-600 dark:text-amber-300">Live updates are unavailable; the latest saved data is still shown.</span>
        </div>
    </div>
</section>
