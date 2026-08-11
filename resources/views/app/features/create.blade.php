@extends('layouts.app')

@section('title', 'New feature')
@section('page-title', 'New feature')

@section('content')
    <div class="mx-auto max-w-4xl">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="new-feature-title">
            <div class="max-w-2xl">
                <h2 id="new-feature-title" class="text-xl font-bold">Add feature work</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">Create work directly for a system. This skips requester approval because it is entered by the IT team.</p>
            </div>

            <form method="POST" action="{{ route('internal.features.store', $workspace) }}" class="mt-7 space-y-5" x-data="{ ai: {{ old('generate_with_ai', true) ? 'true' : 'false' }} }">
                @csrf
                <x-form-errors />

                <div>
                    <x-label for="system_public_id">System</x-label>
                    <x-select id="system_public_id" name="system_public_id" required autofocus>
                        <option value="">Choose a system</option>
                        @foreach ($systems as $system)
                            <option value="{{ $system->public_id }}" @selected(old('system_public_id') === $system->public_id)>{{ $system->name }}</option>
                        @endforeach
                    </x-select>
                    <x-field-error name="system_public_id" />
                </div>

                <div>
                    <x-label for="name">Feature name</x-label>
                    <x-input id="name" name="name" value="{{ old('name') }}" maxlength="200" required />
                    <x-field-error name="name" />
                </div>

                <div>
                    <x-label for="description">Description</x-label>
                    <x-textarea id="description" name="description" rows="5" maxlength="5000" required x-bind:minlength="ai ? 80 : 20" placeholder="Current problem, expected behavior, users, acceptance criteria, and constraints…">{{ old('description') }}</x-textarea>
                    <p class="mt-1 text-xs text-slate-500">When AI drafting is enabled, provide at least 80 characters so it can produce useful tasks and checklists.</p>
                    <x-field-error name="description" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div><x-label for="starts_at">Start date</x-label><x-input id="starts_at" type="date" name="starts_at" value="{{ old('starts_at') }}" /><x-field-error name="starts_at" /></div>
                    <div><x-label for="due_at">Due date</x-label><x-input id="due_at" type="date" name="due_at" value="{{ old('due_at') }}" /><x-field-error name="due_at" /></div>
                </div>

                <label class="flex items-start gap-3 rounded-2xl border border-orbit-200 bg-orbit-50/60 p-4 dark:border-orbit-900/70 dark:bg-orbit-950/30">
                    <input type="checkbox" name="generate_with_ai" value="1" x-model="ai" class="mt-0.5 rounded border-slate-300 text-orbit-600 focus:ring-orbit-500" @checked(old('generate_with_ai', true))>
                    <span><span class="block text-sm font-bold">Draft tasks with AI</span><span class="mt-1 block text-xs leading-5 text-slate-500">AI proposes tasks and to-do checklists. Review the plan before any task is added to the board.</span></span>
                </label>

                <div class="flex flex-wrap gap-3">
                    <x-button>Create feature</x-button>
                    <x-link-button href="{{ route('app.features.index', $workspace) }}" variant="secondary">Cancel</x-link-button>
                </div>
            </form>
        </section>
    </div>
@endsection
