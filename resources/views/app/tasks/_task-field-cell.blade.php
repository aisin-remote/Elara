@if ($field['kind'] === 'custom')
    @include('app.tasks._property-cell', ['task' => $task, 'property' => $field['property'], 'canEditTask' => $canEditTask])
@elseif ($field['key'] === 'title')
    <td class="min-w-64 px-3 py-3">
        <div class="relative flex items-center gap-1.5">
            @if ($canEditTask)
                <input
                    type="text"
                    x-model="values.title"
                    x-on:keydown.enter.prevent="$el.blur()"
                    x-on:blur="save('title')"
                    x-bind:disabled="savingField !== null"
                    maxlength="200"
                    required
                    class="h-9 min-w-0 flex-1 rounded-lg border-transparent bg-transparent px-2 text-sm font-semibold hover:border-slate-300 focus:border-orbit-500 focus:ring-orbit-500 disabled:opacity-60 dark:hover:border-slate-600"
                    aria-label="Task name">
                <a href="{{ route('app.tasks.show', $task) }}" class="grid size-8 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-orbit-700 dark:hover:bg-slate-800 dark:hover:text-orbit-300" title="Open task details" aria-label="Open {{ $task->title }} details"><x-icon name="link" class="size-3.5" /></a>
            @else
                <a href="{{ route('app.tasks.show', $task) }}" class="font-semibold hover:text-orbit-700 dark:hover:text-orbit-300">{{ $task->title }}</a>
            @endif
            @if ($task->isBlocked())<span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-200">Blocked</span>@endif
            @if ($canEditTask)<span x-cloak x-show="savingField === 'title'" class="size-3 shrink-0 animate-spin rounded-full border-2 border-slate-200 border-t-orbit-500 dark:border-slate-700 dark:border-t-orbit-400" aria-label="Saving"></span>@endif
        </div>
        @if ($task->milestone)<p class="mt-1 text-[10px] font-semibold text-violet-600">◆ {{ $task->milestone->name }}</p>@endif
    </td>
@elseif ($field['key'] === 'description')
    <td class="max-w-xs min-w-64 px-3 py-3 text-slate-500">
        @if ($canEditTask)
            <div class="relative">
                <input type="text" x-model="values.description" x-on:keydown.enter.prevent="$el.blur()" x-on:blur="save('description')" x-bind:disabled="savingField !== null" maxlength="10000" class="h-9 w-full truncate rounded-lg border-transparent bg-transparent px-2 text-sm hover:border-slate-300 focus:border-orbit-500 focus:ring-orbit-500 disabled:opacity-60 dark:hover:border-slate-600" aria-label="Task description" placeholder="Empty">
                <span x-cloak x-show="savingField === 'description'" class="absolute right-2 top-1/2 size-3 -translate-y-1/2 animate-spin rounded-full border-2 border-slate-200 border-t-orbit-500 dark:border-slate-700 dark:border-t-orbit-400" aria-label="Saving"></span>
            </div>
        @else
            <span class="block truncate">{{ $task->description ?: '—' }}</span>
        @endif
    </td>
@elseif ($field['key'] === 'due_at')
    <td class="min-w-48 px-3 py-3 {{ $task->due_at && ! $task->completed_at && $task->due_at->isPast() ? 'font-semibold text-rose-600' : 'text-slate-500' }}">
        @if ($canEditTask)
            <div class="relative">
                <input type="datetime-local" x-model="values.due_at" x-on:change="save('due_at')" x-bind:disabled="savingField !== null" @if($task->start_at) min="{{ $task->start_at->format('Y-m-d\\TH:i') }}" @endif class="h-9 w-full rounded-lg border-transparent bg-transparent px-2 text-sm hover:border-slate-300 focus:border-orbit-500 focus:ring-orbit-500 disabled:opacity-60 dark:[color-scheme:dark] dark:hover:border-slate-600" aria-label="Task due date">
                <span x-cloak x-show="savingField === 'due_at'" class="absolute right-8 top-1/2 size-3 -translate-y-1/2 animate-spin rounded-full border-2 border-slate-200 border-t-orbit-500 dark:border-slate-700 dark:border-t-orbit-400" aria-label="Saving"></span>
            </div>
        @else
            {{ $task->due_at?->format('M j, Y') ?? '—' }}
        @endif
    </td>
@elseif ($field['key'] === 'assignees')
    <td class="min-w-56 px-3 py-3">
        @if ($canEditTask)
            <div class="relative">
                <button type="button" x-on:click="assigneeEditor = ! assigneeEditor" x-bind:disabled="savingField !== null" class="flex min-h-9 w-full items-center justify-between gap-2 rounded-lg border border-transparent px-2 text-left text-sm hover:border-slate-300 disabled:opacity-60 dark:hover:border-slate-600" aria-label="Edit task assignees">
                    <span class="truncate" x-text="assigneeNames()"></span>
                    <x-icon name="chevron-right" class="size-3 text-slate-400 transition-transform" x-bind:class="assigneeEditor && 'rotate-90'" />
                </button>
                <div x-cloak x-show="assigneeEditor" class="mt-1 space-y-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="max-h-40 space-y-1 overflow-y-auto">
                        @foreach ($projectMembers as $membership)
                            <label data-assignee-id="{{ $membership->user->public_id }}" data-assignee-name="{{ $membership->user->name }}" class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800">
                                <input type="checkbox" value="{{ $membership->user->public_id }}" x-model="values.assignees" class="rounded border-slate-300 text-orbit-600 focus:ring-orbit-500">
                                <span class="min-w-0 truncate text-sm">{{ $membership->user->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-2 dark:border-slate-800">
                        <button type="button" x-on:click="cancelAssignees()" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">Cancel</button>
                        <button type="button" x-on:click="save('assignees').then((saved) => { if (saved) assigneeEditor = false })" x-bind:disabled="savingField !== null" class="inline-flex items-center gap-1 rounded-lg bg-orbit-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-orbit-700 disabled:opacity-60">
                            <span x-cloak x-show="savingField === 'assignees'" class="size-3 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                            Save
                        </button>
                    </div>
                </div>
            </div>
        @else
            {{ $task->assignees->pluck('first_name')->join(', ') ?: '—' }}
        @endif
    </td>
@elseif ($field['key'] === 'priority')
    <td class="min-w-36 px-3 py-3">
        @if ($canEditTask)
            <div class="relative">
                <select x-model="values.priority" x-on:change="save('priority')" x-bind:disabled="savingField !== null" class="h-9 w-full rounded-lg border-transparent bg-slate-100 py-1 pl-2 pr-8 text-xs font-semibold hover:border-slate-300 focus:border-orbit-500 focus:ring-orbit-500 disabled:opacity-60 dark:bg-slate-800 dark:hover:border-slate-600" aria-label="Task priority">
                    @foreach ($priorities as $priority)<option value="{{ $priority->value }}">{{ $priority->label() }}</option>@endforeach
                </select>
                <span x-cloak x-show="savingField === 'priority'" class="absolute right-8 top-1/2 size-3 -translate-y-1/2 animate-spin rounded-full border-2 border-slate-200 border-t-orbit-500 dark:border-slate-700 dark:border-t-orbit-400" aria-label="Saving"></span>
            </div>
        @else
            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold dark:bg-slate-800">{{ $task->priority->label() }}</span>
        @endif
    </td>
@endif
