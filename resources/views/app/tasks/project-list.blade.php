@extends('layouts.app')

@section('title', ($selectedFeature?->name ?? $project->name).' Tasks')
@section('page-title', $selectedFeature?->name ?? $project->name)

@section('content')
    @php
        $canBulkEdit = auth()->user()->can('create', [App\Models\Task::class, $project]);
        $canManageWorkflow = auth()->user()->can('manageWorkflow', [App\Models\Task::class, $project]);
        $tableColumnCount = 1 + $taskFields->count() + ($canManageWorkflow ? 1 : 0);
        $statusIds = $statuses->pluck('public_id')->all();
    @endphp

    <div x-data="taskDatabase" x-on:resize.window="closeProperty()">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500">Projects / {{ $project->name }}@if($selectedFeature) / {{ $selectedFeature->name }}@endif</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">{{ $selectedFeature ? 'Feature tasks' : 'Project database' }}</h2>
            <p class="mt-1 text-sm text-slate-500">Group tasks by workflow status or any visible Select property.</p>
        </div>
        @can('create', [App\Models\Task::class, $project])
            <x-button type="button" x-on:click="openTask()"><x-icon name="plus" />Add task</x-button>
        @endcan
    </div>

    @if ($project->isSystem())
        @include('app.features._tabs', ['workspace' => $workspace, 'system' => $project, 'feature' => $selectedFeature])
    @else
        @include('app.projects._tabs', ['project' => $project])
    @endif

    <form method="GET" class="mt-5 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
        @if($selectedFeature)<input type="hidden" name="feature" value="{{ $selectedFeature->public_id }}">@endif
        <div class="relative min-w-64 flex-1"><x-icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><x-input name="search" value="{{ request('search') }}" placeholder="Search task" class="pl-9" /></div>
        <div class="min-w-48">
            <label for="group-by" class="sr-only">Group by</label>
            <x-select id="group-by" name="group_by" onchange="this.form.submit()" aria-label="Group tasks by property">
                @foreach ($groupByOptions as $option)
                    <option value="{{ $option['key'] }}" @selected($groupBy === $option['key'])>Group by: {{ $option['name'] }}</option>
                @endforeach
            </x-select>
        </div>
        <x-select name="priority" class="sm:max-w-44"><option value="">All priorities</option>@foreach($priorities as $priority)<option value="{{ $priority->value }}" @selected(request('priority') === $priority->value)>{{ $priority->label() }}</option>@endforeach</x-select>
        <x-select name="blocked" class="sm:max-w-44"><option value="">All readiness</option><option value="1" @selected(request()->boolean('blocked'))>Blocked only</option></x-select>
        <x-button variant="secondary">Filter</x-button>
    </form>

    <div class="mt-4">
        @if ($canBulkEdit)
            <form id="bulk-task-form" method="POST" action="{{ route('internal.tasks.bulk', $project) }}" x-data="{ action: 'status' }" class="flex flex-wrap items-center gap-2 rounded-xl bg-slate-100 p-2 dark:bg-slate-800">
                @csrf
                <span class="px-2 text-xs font-bold uppercase tracking-wide text-slate-500">Bulk action</span>
                <x-select name="action" x-model="action" class="sm:max-w-40"><option value="status">Change status</option><option value="priority">Change priority</option><option value="assignee">Set assignee</option><option value="archive">Archive</option></x-select>
                <x-select name="status_public_id" x-show="action === 'status'" class="sm:max-w-44">@foreach($statuses as $status)<option value="{{ $status->public_id }}">{{ $status->name }}</option>@endforeach</x-select>
                <x-select name="priority" x-show="action === 'priority'" class="sm:max-w-40">@foreach($priorities as $priority)<option value="{{ $priority->value }}">{{ $priority->label() }}</option>@endforeach</x-select>
                <x-select name="assignee_public_id" x-show="action === 'assignee'" class="sm:max-w-48">@foreach($projectMembers as $membership)<option value="{{ $membership->user->public_id }}">{{ $membership->user->name }}</option>@endforeach</x-select>
                <x-button variant="secondary">Apply</x-button>
            </form>
        @endif
    </div>

    <div class="mt-4 space-y-4">
        @foreach ($taskGroups as $taskGroup)
            @php
                $status = $taskGroup['status'];
                $groupTasks = $taskGroup['tasks'];
            @endphp
            <section data-task-group-name="{{ $taskGroup['name'] }}" class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" x-data="{ open: true }">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <div class="flex min-w-0 items-center gap-2">
                        <button type="button" class="grid size-7 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="open = ! open" :aria-expanded="open" aria-label="Toggle {{ $taskGroup['name'] }} group">
                            <x-icon name="chevron-right" class="size-3 transition-transform" x-bind:class="open && 'rotate-90'" />
                        </button>
                        <span class="size-2.5 shrink-0 rounded-full" style="background:{{ $taskGroup['color'] }}"></span>
                        <h3 class="truncate font-bold">{{ $taskGroup['name'] }}</h3>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-slate-800">{{ $groupTasks->count() }}</span>

                        @if ($status)
                        @can('update', $status)
                            <details class="relative">
                                <summary class="grid size-7 cursor-pointer list-none place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Edit {{ $status->name }} group">•••</summary>
                                <div class="absolute left-0 z-30 mt-2 w-[min(88vw,300px)] rounded-2xl border border-slate-200 bg-white p-3 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                                    <form method="POST" action="{{ route('internal.task-statuses.update', $status) }}" class="space-y-2">
                                        @csrf
                                        @method('PATCH')
                                        <x-label for="status-name-{{ $status->public_id }}">Group name</x-label>
                                        <x-input id="status-name-{{ $status->public_id }}" name="name" value="{{ $status->name }}" required maxlength="80" />
                                        <div class="grid grid-cols-[64px_1fr] gap-2">
                                            <x-input type="color" name="color" value="{{ $status->color }}" aria-label="Group color" />
                                            <x-select name="category" aria-label="Group behavior">@foreach(App\Enums\TaskStatusCategory::cases() as $category)<option value="{{ $category->value }}" @selected($status->category === $category)>{{ $category->label() }}</option>@endforeach</x-select>
                                        </div>
                                        <x-button variant="secondary" class="w-full">Save group</x-button>
                                    </form>

                                    <div class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                                        @if (!$loop->first)
                                            <form method="POST" action="{{ route('internal.task-statuses.reorder', $project) }}">
                                                @csrf
                                                @foreach (array_values(array_merge(array_slice($statusIds, 0, $loop->index - 1), [$status->public_id, $statusIds[$loop->index - 1]], array_slice($statusIds, $loop->index + 1))) as $statusId)
                                                    <input type="hidden" name="status_public_ids[]" value="{{ $statusId }}">
                                                @endforeach
                                                <x-button variant="secondary" class="w-full">↑ Up</x-button>
                                            </form>
                                        @else
                                            <span></span>
                                        @endif
                                        @if (!$loop->last)
                                            <form method="POST" action="{{ route('internal.task-statuses.reorder', $project) }}">
                                                @csrf
                                                @foreach (array_values(array_merge(array_slice($statusIds, 0, $loop->index), [$statusIds[$loop->index + 1], $status->public_id], array_slice($statusIds, $loop->index + 2))) as $statusId)
                                                    <input type="hidden" name="status_public_ids[]" value="{{ $statusId }}">
                                                @endforeach
                                                <x-button variant="secondary" class="w-full">↓ Down</x-button>
                                            </form>
                                        @endif
                                    </div>

                                    @if ($statuses->count() > 1)
                                        <form method="POST" action="{{ route('internal.task-statuses.destroy', $status) }}" class="mt-3 space-y-2 border-t border-slate-100 pt-3 dark:border-slate-800" onsubmit="return confirm('Delete this group? This can be recovered from the database if needed.')">
                                            @csrf
                                            @method('DELETE')
                                            @if ($status->tasks_count > 0)
                                                <x-label for="replacement-status-{{ $status->public_id }}">Move {{ $status->tasks_count }} task{{ $status->tasks_count === 1 ? '' : 's' }} to</x-label>
                                                <x-select id="replacement-status-{{ $status->public_id }}" name="replacement_status_public_id" required><option value="">Choose group…</option>@foreach($statuses->where('id', '!=', $status->id) as $replacement)<option value="{{ $replacement->public_id }}">{{ $replacement->name }}</option>@endforeach</x-select>
                                            @endif
                                            <x-button variant="danger" class="w-full">Delete group</x-button>
                                        </form>
                                    @else
                                        <p class="mt-3 border-t border-slate-100 pt-3 text-xs leading-5 text-slate-500 dark:border-slate-800">This is the last group and cannot be deleted.</p>
                                    @endif
                                </div>
                            </details>
                        @endcan
                        @endif
                    </div>

                    @can('create', [App\Models\Task::class, $project])
                        <button type="button" class="inline-flex min-h-9 items-center gap-1 rounded-lg px-3 text-sm font-semibold text-orbit-700 hover:bg-orbit-50 dark:text-orbit-300 dark:hover:bg-orbit-950/50" x-on:click="openTask(@js($taskGroup['defaults']))"><x-icon name="plus" />Add task</button>
                    @endcan
                </header>

                <div x-show="open" class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-left text-sm">
                        <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wide text-slate-400 dark:bg-slate-900">
                            <tr>
                                <th class="w-12 px-4 py-3"><span class="sr-only">Select</span></th>
                                @foreach ($taskFields as $field)
                                    @php
                                        $fieldPanel = $field['kind'] === 'system' ? 'system:'.$field['key'] : $field['key'];
                                        $fieldType = $field['kind'] === 'custom'
                                            ? $field['property']->type->label()
                                            : match ($field['type']) {
                                                'people' => 'People',
                                                'date' => 'Date',
                                                'select' => 'Select',
                                                default => 'Text',
                                            };
                                    @endphp
                                    <th class="min-w-44 px-3 py-3 normal-case tracking-normal">
                                        @if ($canManageWorkflow)
                                            <button type="button" class="group/property flex w-full items-center gap-1.5 rounded-lg text-left hover:text-orbit-700 dark:hover:text-orbit-300" x-on:click="openProperty(@js($fieldPanel), $event)" aria-label="Edit {{ $field['name'] }} property">
                                                <span class="font-semibold text-slate-500 group-hover/property:text-orbit-700 dark:text-slate-300 dark:group-hover/property:text-orbit-300">{{ $field['name'] }}</span>
                                                <span class="text-[10px] font-normal text-slate-400">{{ $fieldType }}</span>
                                                <x-icon name="chevron-right" class="ml-auto size-3 rotate-90 opacity-0 transition-opacity group-hover/property:opacity-100" />
                                            </button>
                                        @else
                                            <span class="font-semibold text-slate-500 dark:text-slate-300">{{ $field['name'] }}</span><span class="ml-1 text-[10px] font-normal text-slate-400">{{ $fieldType }}</span>
                                        @endif
                                    </th>
                                @endforeach
                                @if ($canManageWorkflow)
                                    <th class="w-14 px-2 py-2">
                                        <button type="button" class="grid size-8 place-items-center rounded-lg border border-transparent text-slate-400 transition hover:border-orbit-200 hover:bg-orbit-50 hover:text-orbit-700 dark:hover:border-orbit-900 dark:hover:bg-orbit-950/50 dark:hover:text-orbit-300" x-on:click="openProperty('new', $event)" aria-label="Add property" title="Add property">
                                            <x-icon name="plus" class="size-4" />
                                        </button>
                                    </th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($groupTasks as $task)
                                @php
                                    $canEditTask = auth()->user()->can('update', $task);
                                @endphp
                                <tr
                                    @if ($canEditTask)
                                        x-data="inlineTaskRow({
                                            url: @js(route('internal.tasks.fields.update', $task)),
                                            version: {{ $task->version }},
                                            initial: {
                                                title: @js($task->title),
                                                description: @js($task->description),
                                                due_at: @js($task->due_at?->format('Y-m-d\\TH:i')),
                                                assignees: @js($task->assignees->pluck('public_id')->values()->all()),
                                                priority: @js($task->priority->value),
                                            },
                                            reloadFields: @js($groupBy === 'priority' ? ['priority'] : []),
                                        })"
                                    @endif
                                    class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-3">@if($canBulkEdit)<input form="bulk-task-form" type="checkbox" name="task_public_ids[]" value="{{ $task->public_id }}" class="rounded border-slate-300 text-orbit-600 focus:ring-orbit-500" aria-label="Select {{ $task->title }}">@endif</td>
                                    @foreach ($taskFields as $field)
                                        @include('app.tasks._task-field-cell', ['field' => $field, 'task' => $task, 'canEditTask' => $canEditTask])
                                    @endforeach
                                    @if ($canManageWorkflow)<td class="px-2 py-3"></td>@endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $tableColumnCount }}" class="px-4 py-8 text-center text-sm text-slate-500">No tasks in this group.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>

    @if ($canManageWorkflow)
        <template x-teleport="body">
            <div x-cloak x-show="propertyPanel !== null" x-transition.opacity
                x-bind:style="`left: ${propertyPosition.left}px; top: ${propertyPosition.top}px`"
                x-on:click.outside="closeProperty()" x-on:keydown.escape.window="closeProperty()"
                class="fixed z-[80] max-h-[calc(100vh-2rem)] w-[min(384px,calc(100vw-2rem))] overflow-y-auto rounded-xl border border-slate-200 bg-white p-4 text-left shadow-2xl dark:border-slate-700 dark:bg-slate-900"
                role="dialog" aria-label="Task property editor">
                @include('app.tasks._property-manager', ['editorKey' => $project->public_id])
            </div>
        </template>
    @endif

    @if ($canManageWorkflow && $groupBy === 'status')
        <details class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-900">
            <summary class="flex min-h-12 cursor-pointer list-none items-center gap-2 px-4 text-sm font-semibold text-orbit-700 hover:bg-orbit-50 dark:text-orbit-300 dark:hover:bg-orbit-950/30">
                <x-icon name="plus" class="size-4" />Add group
            </summary>
            <form method="POST" action="{{ route('internal.task-statuses.store', $project) }}" class="grid gap-3 border-t border-slate-200 p-4 sm:grid-cols-[minmax(220px,1fr)_72px_190px_auto] sm:items-end dark:border-slate-800">
                @csrf
                <div>
                    <x-label for="new-group-name">Group name</x-label>
                    <x-input id="new-group-name" name="name" placeholder="e.g. Bugs" required maxlength="80" />
                </div>
                <div>
                    <x-label for="new-group-color">Color</x-label>
                    <x-input id="new-group-color" type="color" name="color" value="#6366f1" />
                </div>
                <div>
                    <x-label for="new-group-category">Behavior</x-label>
                    <x-select id="new-group-category" name="category">
                        @foreach (App\Enums\TaskStatusCategory::cases() as $category)
                            <option value="{{ $category->value }}">{{ $category->label() }}</option>
                        @endforeach
                    </x-select>
                </div>
                <x-button><x-icon name="plus" />Create group</x-button>
            </form>
        </details>
    @endif

    <div class="mt-5">{{ $tasks->links() }}</div>

    @if ($archivedTasks->isNotEmpty())
        <details class="mt-8 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <summary class="cursor-pointer font-bold">Archived tasks ({{ $archivedTasks->count() }})</summary>
            <div class="mt-4 space-y-2">@foreach($archivedTasks as $archivedTask)<div class="flex items-center justify-between rounded-xl bg-slate-50 p-3 dark:bg-slate-800"><span class="text-sm font-semibold">{{ $archivedTask->title }}</span>@can('restore',$archivedTask)<form method="POST" action="{{ route('internal.tasks.restore',$archivedTask) }}">@csrf<x-button variant="secondary">Restore</x-button></form>@endcan</div>@endforeach</div>
        </details>
    @endif

    @can('update', $workspace)
        <details class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <summary class="cursor-pointer text-sm font-bold">Task categories</summary>
            <form method="POST" action="{{ route('internal.task-categories.store', $workspace) }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_100px_auto]">@csrf<x-input name="name" placeholder="Category name" required /><x-input type="color" name="color" value="#8b5cf6" /><x-button>Add category</x-button></form>
        </details>
    @endcan

    @can('create', [App\Models\Task::class, $project])
        @include('app.tasks._create-dialog', ['defaultStatus' => $statuses->first()])
    @endcan
    </div>
@endsection
