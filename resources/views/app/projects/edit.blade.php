@extends('layouts.app')

@section('title', 'Edit '.$project->name)
@section('page-title', 'Edit project')

@section('content')
    <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900">
        <form method="POST" action="{{ route('internal.projects.update', $project) }}" class="space-y-5">
            @csrf @method('PATCH')
            <input type="hidden" name="version" value="{{ $project->version }}">
            <div class="grid gap-5 xl:grid-cols-2">
                <div><x-label for="name">Project name</x-label><x-input id="name" name="name" value="{{ old('name', $project->name) }}" required autofocus /><x-field-error name="name" /></div>
                <div><x-label for="description">Description</x-label><x-textarea id="description" name="description">{{ old('description', $project->description) }}</x-textarea><x-field-error name="description" /></div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <div><x-label for="status">Status</x-label><x-select id="status" name="status">@foreach (['planned' => 'Planned', 'active' => 'Active', 'on_hold' => 'On hold', 'completed' => 'Completed'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $project->status->value) === $value)>{{ $label }}</option>@endforeach</x-select></div>
                <div><x-label for="color">Color</x-label><x-input id="color" type="color" name="color" value="{{ old('color', $project->color ?? '#4f46e5') }}" /></div>
                <div><x-label for="start_date">Start date</x-label><x-input id="start_date" type="date" name="start_date" value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}" /></div>
                <div><x-label for="due_date">Due date</x-label><x-input id="due_date" type="date" name="due_date" value="{{ old('due_date', $project->due_date?->format('Y-m-d')) }}" /><x-field-error name="due_date" /></div>
            </div>
            <x-field-error name="version" />
            <div class="flex gap-3"><x-button>Save project</x-button><x-link-button href="{{ route('app.projects.show', $project) }}" variant="secondary">Cancel</x-link-button></div>
        </form>
    </div>
@endsection
