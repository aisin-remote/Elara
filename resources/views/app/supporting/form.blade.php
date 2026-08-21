@extends('layouts.app')

@php($editing = $task !== null)
@section('title', $editing ? 'Edit supporting task' : 'New supporting task')
@section('page-title', $editing ? 'Edit supporting task' : 'New supporting task')

@section('content')
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px] xl:items-start">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="supporting-form-title">
            <h2 id="supporting-form-title" class="text-xl font-bold">{{ $editing ? 'Update operational work' : 'Register operational work' }}</h2>
            <p class="mt-1 text-sm text-slate-500">No project, system, or feature is required.</p>

            <form method="POST" action="{{ $editing ? route('internal.supporting-tasks.update', $task) : route('internal.supporting-tasks.store', $workspace) }}" class="mt-6 space-y-5">
                @csrf
                @if ($editing) @method('PATCH') @endif
                <div><x-label for="title">Task title</x-label><x-input id="title" name="title" value="{{ old('title', $task?->title) }}" placeholder="e.g. Repair finance printer" required autofocus /><x-field-error name="title" /></div>
                <div><x-label for="description">Description</x-label><x-textarea id="description" name="description" rows="5" placeholder="Add the request, location, expected result, or other useful context.">{{ old('description', $task?->description) }}</x-textarea><x-field-error name="description" /></div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><x-label for="category">Category</x-label><x-select id="category" name="category" required>@foreach($categories as $option)<option value="{{ $option->value }}" @selected(old('category', $task?->category->value ?? 'other') === $option->value)>{{ $option->label() }}</option>@endforeach</x-select><x-field-error name="category" /></div>
                    <div><x-label for="priority">Priority</x-label><x-select id="priority" name="priority" required>@foreach($priorities as $option)<option value="{{ $option->value }}" @selected(old('priority', $task?->priority->value ?? 'medium') === $option->value)>{{ $option->label() }}</option>@endforeach</x-select><x-field-error name="priority" /></div>
                    <div><x-label for="status">Status</x-label><x-select id="status" name="status" required>@foreach($statuses as $option)<option value="{{ $option->value }}" @selected(old('status', $task?->status->value ?? 'todo') === $option->value)>{{ $option->label() }}</option>@endforeach</x-select><x-field-error name="status" /></div>
                    <div><x-label for="assignee_public_id">Assignee</x-label><x-select id="assignee_public_id" name="assignee_public_id"><option value="">Unassigned</option>@foreach($members as $membership)<option value="{{ $membership->user->public_id }}" @selected(old('assignee_public_id', $task?->assignee?->public_id) === $membership->user->public_id)>{{ $membership->user->name }}</option>@endforeach</x-select><x-field-error name="assignee_public_id" /></div>
                    <div><x-label for="due_date">Due date</x-label><x-input id="due_date" type="date" name="due_date" value="{{ old('due_date', $task?->due_date?->format('Y-m-d')) }}" /><x-field-error name="due_date" /></div>
                </div>
                <div class="flex flex-wrap gap-3"><x-button>{{ $editing ? 'Save changes' : 'Create supporting task' }}</x-button><x-link-button href="{{ route('app.supporting.index', $workspace) }}" variant="secondary">Cancel</x-link-button></div>
            </form>

            @if ($editing)
                @can('delete', $task)
                    <form method="POST" action="{{ route('internal.supporting-tasks.destroy', $task) }}" class="mt-8 border-t border-slate-200 pt-6 dark:border-slate-800" onsubmit="return confirm('Archive this supporting task?')">
                        @csrf @method('DELETE')
                        <x-button variant="danger">Archive task</x-button>
                    </form>
                @endcan
            @endif
        </section>

        <aside class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="supporting-guide-title">
            <span class="grid size-12 place-items-center rounded-2xl bg-orbit-50 text-orbit-700 dark:bg-orbit-950 dark:text-orbit-300"><x-icon name="supporting" /></span>
            <h2 id="supporting-guide-title" class="mt-5 text-lg font-bold">What belongs here?</h2>
            <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-500">
                <li><span class="font-semibold text-slate-700 dark:text-slate-200">Presentation:</span> build a PowerPoint or supporting document.</li>
                <li><span class="font-semibold text-slate-700 dark:text-slate-200">Hardware:</span> repair a printer, laptop, or peripheral.</li>
                <li><span class="font-semibold text-slate-700 dark:text-slate-200">Software:</span> install an application or resolve an account issue.</li>
                <li><span class="font-semibold text-slate-700 dark:text-slate-200">Network:</span> investigate connectivity or access.</li>
            </ul>
        </aside>
    </div>
    @if($task)<x-discussion :subject="$task" />@endif
@endsection
