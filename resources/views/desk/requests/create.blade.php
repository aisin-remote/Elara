@extends('layouts.requester')

@section('title', 'New request')
@section('page-title', 'New request')

@section('content')
    <div class="border-b border-slate-200 pb-6 dark:border-slate-800">
        <h2 class="text-2xl font-bold tracking-tight">Request a change</h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
            Describe the problem and what a good outcome looks like. A supervisor will review it,
            then ITD will turn it into planned work. You do not need to design the solution.
        </p>
    </div>

    <x-auth-errors class="mt-6" />

    @if ($organizationProfile)
        <x-alert variant="info" :dismissible="false" class="mt-6 max-w-none" title="Your approval path">
            {{ $organizationProfile['department_name'] }} · {{ $organizationProfile['rank_code'] }}.
            {{ $needsDepartmentApproval
                ? 'This request goes to your department manager/coordinator first, then to an ITD supervisor.'
                : 'This request goes directly to an ITD supervisor.' }}
        </x-alert>
    @else
        <x-alert variant="warning" :dismissible="false" class="mt-6 max-w-none" title="Organization profile not connected">
            Make sure your email, job rank, and department are available in the company directory before submitting a request.
        </x-alert>
    @endif

    @include('desk._how-it-works', [
        'steps' => [
            ['Describe the problem', 'Explain what is difficult today and what outcome you expect. Focus on the need, not how to build it.', 'You'],
            ['Supervisor review', 'The reviewer approves it, rejects it with a reason, or asks you for more information.', 'Supervisor'],
            ['Capacity scheduling', 'Approved requests are scheduled against real team capacity.', 'Automatic'],
            ['Work planning', 'ITD breaks the request into estimated tasks and the assignee reviews the plan before work starts.', 'ITD'],
            ['Validate the result', 'Anything only you can judge pauses until you confirm that it is correct.', 'You'],
            ['Delivered', 'After the final task and validation are complete, the request moves to History.', 'ITD'],
        ],
        'writing' => [
            'Explain the problem rather than prescribing a solution. ITD may know a simpler route to the same result.',
            'Include how often it happens and how much time it consumes.',
            'Select the system you actually use, even if you are unsure where the change belongs.',
            'Mark a request urgent only when waiting creates real harm.',
        ],
    ])

    @if ($systems->isEmpty())
        <x-alert variant="info" :dismissible="false" class="mt-6 max-w-none">
            No systems are currently open for requests. Ask an administrator to add the system under Settings → Master data.
        </x-alert>
    @else
        <form method="POST" action="{{ route('desk.requests.store', $workspace) }}" class="mt-6 space-y-6">
            @csrf

            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <x-label for="system_public_id">Which system?</x-label>
                <x-select id="system_public_id" name="system_public_id" required>
                    <option value="">Select a system</option>
                    @foreach ($systems as $system)
                        <option value="{{ $system->public_id }}" @selected(old('system_public_id') === $system->public_id)>
                            {{ $system->name }}{{ ($queueDepth[$system->public_id] ?? 0) > 0 ? ' — '.$queueDepth[$system->public_id].' ahead of you' : '' }}
                        </option>
                    @endforeach
                </x-select>
                <x-field-error name="system_public_id" />
                <p class="mt-2 text-xs text-slate-500">Each system has an accountable owner; your request is routed there first.</p>
            </section>

            <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <x-label for="title">Short title</x-label>
                    <x-input id="title" name="title" value="{{ old('title') }}" placeholder="Export the monthly stock report" required />
                    <x-field-error name="title" />
                </div>

                <div>
                    <x-label for="problem">What is the current problem?</x-label>
                    <x-textarea id="problem" name="problem" rows="4" placeholder="We copy the figures into a spreadsheet every month. It takes two days and frequently introduces errors.">{{ old('problem') }}</x-textarea>
                    <x-field-error name="problem" />
                </div>

                <div>
                    <x-label for="desired_outcome">What outcome do you expect?</x-label>
                    <x-textarea id="desired_outcome" name="desired_outcome" rows="4" placeholder="I can download the same report directly from the system with the columns we already use.">{{ old('desired_outcome') }}</x-textarea>
                    <x-field-error name="desired_outcome" />
                </div>

                <div>
                    <x-label for="urgency">How urgent is it?</x-label>
                    <x-select id="urgency" name="urgency">
                        @foreach ($urgencies as $urgency)
                            <option value="{{ $urgency->value }}" @selected(old('urgency', 'normal') === $urgency->value)>{{ $urgency->label() }}</option>
                        @endforeach
                    </x-select>
                    <p class="mt-2 text-xs text-slate-500">Urgency helps reviewers prioritize the queue; it does not bypass capacity planning.</p>
                </div>
            </section>

            <div class="flex gap-3">
                <x-button>Submit request</x-button>
                <x-link-button href="{{ route('desk.index') }}" variant="secondary">Cancel</x-link-button>
            </div>
        </form>
    @endif
@endsection
