<div
    class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(300px,.65fr)]"
    x-data="{ selection: { category: '', period: '', tasks: [] } }"
    @task-performance-open.window="selection = $event.detail; $refs.taskPerformanceDialog.showModal()"
>
    <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm shadow-slate-950/[.02] dark:border-slate-800 dark:bg-slate-900 sm:p-4" aria-labelledby="task-performance-title">
        <h3 id="task-performance-title" class="px-1 pb-4 text-lg font-bold">Task Performance</h3>
        <div class="rounded-2xl border border-slate-200 p-4 shadow-sm shadow-slate-950/[.03] dark:border-slate-700 sm:p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-x-5 gap-y-2 text-xs font-medium text-slate-500 dark:text-slate-300">
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-emerald-500"></span>Complete</span>
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full border border-slate-300 bg-slate-50 dark:border-slate-500 dark:bg-slate-700"></span>New Task</span>
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-rose-500"></span>Overdue</span>
                </div>
                <span class="inline-flex min-h-10 w-fit shrink-0 items-center gap-2 rounded-xl border border-slate-200 px-3 text-xs font-medium text-slate-500 dark:border-slate-700 dark:text-slate-300"><x-icon name="calendar" class="size-4" />{{ $dashboard['period']['label'] }}</span>
            </div>
            <div class="mt-4 h-64 sm:h-72">
                <canvas class="cursor-pointer" data-orbitra-chart="performance" data-chart-source="dashboard-trend" aria-label="Task performance chart for {{ $dashboard['period']['label'] }}. Click a bar segment to view its tasks." role="img"></canvas>
            </div>
            <p class="mt-2 text-center text-[11px] text-slate-400">Click a bar segment to view its tasks.</p>
        </div>
        <script id="dashboard-trend" type="application/json">@json($dashboard['trend'])</script>
    </section>

    <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="member-task-title">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 id="member-task-title" class="text-lg font-bold">Member task workload</h3>
                <p class="mt-1 text-xs text-slate-500">Open vs completed tasks by member</p>
            </div>
            <span class="shrink-0 rounded-full bg-orbit-50 px-3 py-1 text-xs font-bold text-orbit-700 dark:bg-orbit-950 dark:text-orbit-300">{{ $dashboard['member_task_heatmap']['total_tasks'] }} tasks</span>
        </div>
        @if(count($dashboard['member_task_heatmap']['members']) > 0)
            <div class="mt-5 h-64"><canvas data-orbitra-chart="workload" data-chart-source="dashboard-member-task" aria-label="Member task workload chart" role="img"></canvas></div>
            <script id="dashboard-member-task" type="application/json">@json($dashboard['member_task_heatmap']['members'])</script>
        @else
            <p class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800">No active members in this workspace.</p>
        @endif
    </section>

    <x-modal id="task-performance-dialog" title="Task details" x-ref="taskPerformanceDialog" class="max-h-[90vh]">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.14em] text-orbit-600 dark:text-orbit-300" x-text="selection.category"></p>
                <p class="mt-1 text-lg font-bold" x-text="selection.period"></p>
            </div>
            <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300" x-text="selection.tasks.length + (selection.tasks.length === 1 ? ' task' : ' tasks')"></span>
        </div>

        <div class="mt-5 max-h-[60vh] space-y-3 overflow-y-auto pr-1">
            <template x-for="task in selection.tasks" :key="task.public_id">
                <article class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-bold" x-text="task.title"></h3>
                            <p class="mt-1 truncate text-xs text-slate-500" x-text="task.project"></p>
                        </div>
                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300" x-text="task.priority"></span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400" x-text="task.description"></p>
                    <dl class="mt-4 grid gap-3 text-xs sm:grid-cols-2">
                        <div><dt class="text-slate-400">Status</dt><dd class="mt-1 font-semibold" x-text="task.status"></dd></div>
                        <div><dt class="text-slate-400">Assignee</dt><dd class="mt-1 font-semibold" x-text="task.assignees.length ? task.assignees.join(', ') : 'Unassigned'"></dd></div>
                        <div><dt class="text-slate-400">Created</dt><dd class="mt-1 font-semibold" x-text="task.created_at"></dd></div>
                        <div><dt class="text-slate-400">Due date</dt><dd class="mt-1 font-semibold" x-text="task.due_at || 'No due date'"></dd></div>
                        <div><dt class="text-slate-400">Completed</dt><dd class="mt-1 font-semibold" x-text="task.completed_at || 'Not completed'"></dd></div>
                    </dl>
                    <a :href="task.url" class="mt-4 inline-flex items-center gap-2 text-xs font-bold text-orbit-700 hover:text-orbit-800 dark:text-orbit-300 dark:hover:text-orbit-200">
                        Open full task <x-icon name="chevron-right" class="size-3.5" />
                    </a>
                </article>
            </template>
        </div>
    </x-modal>
</div>
