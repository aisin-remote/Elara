@php
    $dialogTaskFields = $taskFields ?? app(App\Services\TaskFieldSchema::class)->visibleFields($project);
@endphp

<dialog id="new-task-dialog"
    class="m-0 h-full max-h-none w-full max-w-none bg-transparent p-0 backdrop:bg-slate-950/45 sm:m-auto sm:h-auto sm:max-h-[90vh] sm:w-[720px] sm:rounded-3xl"
    x-ref="taskDialog"
    @if ($errors->any() || request()->boolean('create'))
        x-init="$nextTick(() => $el.showModal())"
    @endif>
    <div class="h-full overflow-y-auto bg-white p-5 shadow-2xl sm:rounded-3xl sm:p-7 dark:bg-slate-900">
        <div class="mb-6 flex items-start justify-between"><div><p class="text-xs font-bold uppercase tracking-[.16em] text-orbit-600">{{ $project->name }}</p><h2 class="mt-1 text-xl font-bold">Add new task</h2></div><button type="button" class="grid size-10 place-items-center rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" onclick="this.closest('dialog').close()" aria-label="Close task form">✕</button></div>
        @include('app.tasks._create-form', ['defaultStatus' => $statuses->first(), 'taskFields' => $dialogTaskFields])
    </div>
</dialog>
