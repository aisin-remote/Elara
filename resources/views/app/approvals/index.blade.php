@extends('layouts.app')

@section('title', 'Approvals')
@section('page-title', 'Approvals')

@php
    $waiting = array_sum($counts);
    $tabs = [
        'feature' => ['Feature requests', $counts['feature']],
        'project' => ['Project requests', $counts['project']],
        'plans' => ['Proposed plans', $counts['plans']],
        'decided' => ['History', null],
    ];
    $head = 'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400';
@endphp

@section('content')
    <div class="pb-5">
        <h2 class="text-2xl font-bold tracking-tight">{{ $waiting }} waiting on the team</h2>
        <p class="mt-2 text-sm text-slate-500">Requests from people outside the team. Urgent first, then oldest.</p>
    </div>

    {{-- Tabs are links, not Alpine state: every action here redirects back, and a tab that
         resets to the first one after each decision would fight the person using it. --}}
    <x-tabs label="Approval queues">
        @foreach ($tabs as $key => [$label, $count])
            <a href="{{ route('app.approvals.index', ['workspace' => $workspace, 'tab' => $key]) }}"
                @if ($tab === $key) aria-current="page" @endif
                class="flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold {{ $tab === $key ? 'border-orbit-500 text-orbit-700 dark:text-orbit-300' : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-white' }}">
                {{ $label }}
                @if ($count)
                    <span class="rounded-full bg-slate-100 px-2 text-xs tabular-nums dark:bg-slate-800">{{ $count }}</span>
                @endif
            </a>
        @endforeach
    </x-tabs>

    <x-approval-delegation :workspace="$workspace" :delegations="$delegations" :incoming-delegations="$incomingDelegations" :delegation-members="$delegationMembers" :delegation-scopes="$delegationScopes" />

    <div class="mt-6">
        @if ($tab === 'feature')
            @if ($pending->isEmpty())
                <x-empty-state icon="check" title="Nothing waiting" description="New feature requests appear here the moment someone submits one." />
            @else
                <x-table class="bg-white dark:bg-slate-900">
                    <thead class="border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th scope="col" class="{{ $head }} w-12 text-right">#</th>
                            <th scope="col" class="{{ $head }}">Request</th>
                            <th scope="col" class="{{ $head }}">System</th>
                            <th scope="col" class="{{ $head }}">Requester</th>
                            <th scope="col" class="{{ $head }} whitespace-nowrap">Waiting</th>
                            <th scope="col" class="{{ $head }}"><span class="sr-only">Review</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($pending as $request)
                            {{-- The whole row opens the request; the title stays a real link so
                                 keyboard and middle-click still work. --}}
                            <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60"
                                onclick="window.location='{{ route('app.approvals.show', [$workspace, $request]) }}'">
                                <td class="w-12 px-4 py-3 text-right text-xs tabular-nums text-slate-400">{{ $loop->iteration }}</td>
                                <td class="max-w-sm px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('app.approvals.show', [$workspace, $request]) }}" class="font-semibold hover:underline">{{ $request->title }}</a>
                                        @if ($request->urgency->value === 'high')
                                            <x-badge tone="danger">Urgent</x-badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ $request->system->name }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $request->requester->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $slaByRequest[$request->public_id]['age'] ?? $request->created_at->diffForHumans(syntax: true) }}@if($slaByRequest[$request->public_id] ?? null)<div class="mt-1"><x-badge :tone="$slaByRequest[$request->public_id]['tone']">{{ $slaByRequest[$request->public_id]['label'] }}</x-badge><p class="mt-1 text-[11px]">Owner: {{ $slaByRequest[$request->public_id]['owner'] }}</p></div>@endif</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="text-xs font-semibold text-orbit-600 dark:text-orbit-400">Review</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif

        @elseif ($tab === 'project')
            @if ($pendingProjects->isEmpty())
                <x-empty-state icon="check" title="No project requests waiting" description="Proposals appear here once someone submits a business case." />
            @else
                <x-table class="bg-white dark:bg-slate-900">
                    <thead class="border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th scope="col" class="{{ $head }}">Proposal</th>
                            <th scope="col" class="{{ $head }}">Requester</th>
                            <th scope="col" class="{{ $head }}">Stage</th>
                            <th scope="col" class="{{ $head }} whitespace-nowrap">Waiting</th>
                            <th scope="col" class="{{ $head }}"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingProjects as $projectRequest)
                            <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/60">
                                <td class="max-w-sm px-4 py-3">
                                    <a href="{{ route('app.approvals.projects.show', [$workspace, $projectRequest]) }}" class="font-semibold hover:underline">{{ $projectRequest->title }}</a>
                                    <p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ $projectRequest->benefit }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ $projectRequest->requester->name }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :tone="$projectRequest->status->tone()">{{ $projectRequest->status->label() }}</x-badge>
                                    @if ($projectRequest->spv_at)
                                        <p class="mt-1 text-xs text-slate-400">Signed by {{ $projectRequest->supervisor?->name }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $slaByRequest[$projectRequest->public_id]['age'] ?? $projectRequest->created_at->diffForHumans(syntax: true) }}@if($slaByRequest[$projectRequest->public_id] ?? null)<div class="mt-1"><x-badge :tone="$slaByRequest[$projectRequest->public_id]['tone']">{{ $slaByRequest[$projectRequest->public_id]['label'] }}</x-badge><p class="mt-1 text-[11px]">Owner: {{ $slaByRequest[$projectRequest->public_id]['owner'] }}</p></div>@endif</td>
                                <td class="px-4 py-3 text-right">
                                    {{-- No inline decision: a project request needs the meeting gate and
                                         two distinct signatures, and a one-click approve in a list
                                         invites signing without reading the business case. --}}
                                    <x-link-button href="{{ route('app.approvals.projects.show', [$workspace, $projectRequest]) }}" variant="secondary">Open</x-link-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif

        @elseif ($tab === 'plans')
            <p class="mb-4 text-sm text-slate-500">Drafted and idle. No task reaches a board until one of these is accepted.</p>
            @if ($awaitingAcceptance->isEmpty())
                <x-empty-state icon="sparkles" title="No plans waiting" description="AI plans appear here after an approval or direct IT creation." />
            @else
                <x-table class="bg-white dark:bg-slate-900">
                    <thead class="border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th scope="col" class="{{ $head }}">Work item</th>
                            <th scope="col" class="{{ $head }} whitespace-nowrap">Tasks</th>
                            <th scope="col" class="{{ $head }} whitespace-nowrap">Effort</th>
                            <th scope="col" class="{{ $head }}">Assigned to</th>
                            <th scope="col" class="{{ $head }} whitespace-nowrap">Drafted</th>
                            <th scope="col" class="{{ $head }}"><span class="sr-only">Review</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($awaitingAcceptance as $draft)
                            @php
                                $subject = $draft->subject;
                                $title = $subject?->title ?? $subject?->name ?? 'Unavailable work item';
                                $url = match (true) {
                                    $subject instanceof App\Models\FeatureRequest => route('app.approvals.show', [$workspace, $subject]),
                                    $subject instanceof App\Models\ProjectRequest => route('app.approvals.projects.show', [$workspace, $subject]),
                                    $subject instanceof App\Models\Feature => route('app.features.detail', [$workspace, $subject->project, $subject]),
                                    $subject instanceof App\Models\Project => route('app.projects.show', $subject),
                                    default => route('app.approvals.index', [$workspace, 'tab' => 'plans']),
                                };
                                $assignee = match (true) {
                                    $subject instanceof App\Models\FeatureRequest, $subject instanceof App\Models\ProjectRequest => $subject->assignee,
                                    $subject instanceof App\Models\Feature => $subject->project?->pic(),
                                    $subject instanceof App\Models\Project => $subject->owner,
                                    default => null,
                                };
                            @endphp
                            <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/60">
                                <td class="max-w-sm px-4 py-3"><a href="{{ $url }}" class="font-semibold hover:underline">{{ $title }}</a></td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-500">{{ count($draft->tasks()) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-500">{{ round($draft->totalMinutes() / 60, 1) }}h</td>
                                <td class="px-4 py-3 text-slate-500">{{ $assignee?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $draft->generated_at?->diffForHumans() ?? '—' }}</td>
                                <td class="px-4 py-3 text-right"><x-link-button href="{{ $url }}" variant="secondary">Review</x-link-button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif

        @else
            @if ($recent->isEmpty())
                <x-empty-state icon="clock" title="No decisions yet" description="Once the team decides on a request it is recorded here." />
            @else
                <x-table class="bg-white dark:bg-slate-900">
                    <thead class="border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th scope="col" class="{{ $head }}">Request</th>
                            <th scope="col" class="{{ $head }}">System</th>
                            <th scope="col" class="{{ $head }}">Decided by</th>
                            <th scope="col" class="{{ $head }} whitespace-nowrap">When</th>
                            <th scope="col" class="{{ $head }}">Outcome</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recent as $request)
                            <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/60">
                                <td class="max-w-sm px-4 py-3"><a href="{{ route('app.approvals.show', [$workspace, $request]) }}" class="font-semibold hover:underline">{{ $request->title }}</a></td>
                                <td class="px-4 py-3 text-slate-500">{{ $request->system->name }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $request->reviewer?->name ?? 'the team' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $request->reviewed_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3"><x-badge :tone="$request->status->tone()">{{ $request->status->label() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif
        @endif
    </div>
@endsection
