@extends('layouts.requester')

@section('title', 'New support request')
@section('page-title', 'Supporting')

@section('content')
    <div class="border-b border-slate-200 pb-6 dark:border-slate-800">
        <h2 class="text-2xl font-bold tracking-tight">Request operational support</h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
            Ask ITD for work outside a system feature or project, such as preparing a presentation,
            fixing a printer, resolving an account issue, or checking a network connection.
        </p>
    </div>

    <x-auth-errors class="mt-6" />

    @include('desk._how-it-works', [
        'steps' => [
            ['Describe the support needed', 'Explain the expected result and include the relevant device, file, account, or location.', 'You'],
            ['ITD triage', 'ITD reviews the request, confirms its priority, and assigns the right team member.', 'ITD'],
            ['Support in progress', 'The assignee works on the request and keeps its status up to date.', 'ITD'],
            ['Completed', 'The completed request remains available in your request history.', 'ITD'],
        ],
        'writing' => [
            'Use Supporting only for operational work that does not change a system or require a new project.',
            'Include device names, room locations, file formats, or account names when relevant.',
            'Choose a realistic needed-by date and use high priority only when waiting causes operational impact.',
            'Describe the expected result so ITD knows when the request is complete.',
        ],
    ])

    <form method="POST" action="{{ route('desk.supporting.store', $workspace) }}" class="mt-6 space-y-6">
        @csrf

        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div>
                <x-label for="title">What do you need?</x-label>
                <x-input id="title" name="title" value="{{ old('title') }}" placeholder="Repair the meeting room printer" required autofocus maxlength="200" />
                <x-field-error name="title" />
            </div>

            <div>
                <x-label for="description">Details</x-label>
                <x-textarea id="description" name="description" rows="6" required placeholder="Describe the expected result, relevant device, file, account, location, and anything else ITD should know.">{{ old('description') }}</x-textarea>
                <x-field-error name="description" />
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Request details</h3>
            <p class="mt-1 text-xs text-slate-500">Help ITD route and prioritize the work correctly.</p>
            <div class="mt-5 grid gap-5 md:grid-cols-3">
                <div>
                    <x-label for="category">Category</x-label>
                    <x-select id="category" name="category" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                        @endforeach
                    </x-select>
                    <x-field-error name="category" />
                </div>
                <div>
                    <x-label for="priority">Priority</x-label>
                    <x-select id="priority" name="priority" required>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(old('priority', 'medium') === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </x-select>
                    <x-field-error name="priority" />
                </div>
                <div>
                    <x-label for="needed_by">Needed by</x-label>
                    <x-input id="needed_by" type="date" name="needed_by" value="{{ old('needed_by') }}" />
                    <x-field-error name="needed_by" />
                </div>
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            <x-button>Send support request</x-button>
            <x-link-button href="{{ route('desk.index', ['tab' => 'supporting']) }}" variant="secondary">Cancel</x-link-button>
        </div>
    </form>
@endsection
