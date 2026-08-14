@extends('layouts.requester')

@section('title', 'New support request')
@section('page-title', 'Supporting')

@section('content')
<div class="mx-auto max-w-3xl">
    <div><p class="text-sm font-semibold text-orbit-600">Quick request</p><h2 class="mt-1 text-2xl font-bold">Ask ITD for operational support</h2><p class="mt-2 text-sm text-slate-500">For work outside a system feature or project, such as a presentation, printer, account, or network issue.</p></div>
    <form method="POST" action="{{ route('desk.supporting.store', $workspace) }}" class="mt-6 space-y-5 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">@csrf
        <div><x-label for="title">What do you need?</x-label><x-input id="title" name="title" value="{{ old('title') }}" required autofocus maxlength="200" /><x-field-error name="title" /></div>
        <div><x-label for="description">Details</x-label><x-textarea id="description" name="description" rows="6" required placeholder="Describe the expected result, relevant device/file/location, and anything ITD should know.">{{ old('description') }}</x-textarea><x-field-error name="description" /></div>
        <div class="grid gap-4 sm:grid-cols-3"><div><x-label for="category">Category</x-label><x-select id="category" name="category">@foreach($categories as $category)<option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>@endforeach</x-select></div><div><x-label for="priority">Priority</x-label><x-select id="priority" name="priority">@foreach($priorities as $priority)<option value="{{ $priority->value }}" @selected(old('priority', 'medium') === $priority->value)>{{ $priority->label() }}</option>@endforeach</x-select></div><div><x-label for="needed_by">Needed by</x-label><x-input id="needed_by" type="date" name="needed_by" value="{{ old('needed_by') }}" /></div></div>
        <div class="flex gap-3"><x-button>Send request</x-button><x-link-button href="{{ route('desk.index', ['tab'=>'supporting']) }}" variant="secondary">Cancel</x-link-button></div>
    </form>
</div>
@endsection
