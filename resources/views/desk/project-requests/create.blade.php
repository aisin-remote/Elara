@extends('layouts.requester')

@section('title', 'Propose a project')
@section('page-title', 'Propose a project')

@section('content')
    <div class="border-b border-slate-200 pb-6 dark:border-slate-800">
        <h2 class="text-2xl font-bold tracking-tight">Propose a new project</h2>
        <p class="mt-2 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
            Build the business case below so your department and ITD can assess the problem, intended outcome, benefits, costs, and return.
        </p>
    </div>

    <x-auth-errors class="mt-6" />

    @include('desk._how-it-works', [
        'steps' => [
            ['Build the business case', 'Describe the background, pain point, objectives, before-and-after process, benefits, and expected cost and return.', 'You'],
            ['Department review', 'If required by your job rank, your department manager or coordinator reviews the proposal first.', 'Department'],
            ['Scoping meeting', 'ITD meets with you to clarify scope. No ITD signature can be recorded before this meeting.', 'You and ITD'],
            ['First ITD signature', 'An ITD supervisor reviews the scoped proposal and records the first decision.', 'ITD supervisor'],
            ['Second ITD signature', 'A different ITD manager records the second decision. The same person cannot supply both signatures.', 'ITD manager'],
            ['Planning and delivery', 'Once approved, the project is created, scheduled against real capacity, and broken into tasks.', 'ITD'],
        ],
        'writing' => [
            'Use measurable objectives whenever possible.',
            'Describe the current process honestly, including manual workarounds.',
            'Include both tangible benefits, such as saved hours, and intangible benefits, such as better control or service quality.',
            'The target date is a preference; the committed schedule is set after approval and capacity planning.',
        ],
    ])

    <form method="POST" action="{{ route('desk.project-requests.store', $workspace) }}" class="mt-6 space-y-6">
        @csrf
        @include('desk.project-requests._form-fields')
        <div class="flex flex-wrap gap-3">
            <x-button>Submit project proposal</x-button>
            <x-link-button href="{{ route('desk.index') }}" variant="secondary">Cancel</x-link-button>
        </div>
    </form>
@endsection
