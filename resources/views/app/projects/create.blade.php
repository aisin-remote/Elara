@extends('layouts.app')

@section('title', 'New project')
@section('page-title', 'New project')

@section('content')
    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="existing-projects-title">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 id="existing-projects-title" class="text-xl font-bold">Already in {{ $workspace->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $existingProjects->count() }} {{ \Illuminate\Support\Str::plural('project', $existingProjects->count()) }} you can see. Check here before starting a duplicate.</p>
                </div>
                <a href="{{ route('app.projects.index', $workspace) }}" class="shrink-0 text-xs font-bold text-orbit-700 dark:text-orbit-300">Open list</a>
            </div>

            <div class="mt-6 space-y-3">
                @forelse ($existingProjects as $existing)
                    <a href="{{ route('app.projects.show', $existing) }}" class="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:hover:border-slate-700 dark:hover:bg-slate-800/60">
                        <span class="size-3 shrink-0 rounded-full" style="background: {{ $existing->color ?? '#64748b' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $existing->name }}</span>
                            <span class="mt-0.5 block truncate text-xs text-slate-500">
                                {{ $existing->status->label() }}
                                @if ($existing->due_date)
                                    · due {{ $existing->due_date->format('M j, Y') }}
                                @endif
                            </span>
                        </span>
                        <span class="flex shrink-0 -space-x-1.5">
                            @foreach ($existing->members->take(3) as $member)
                                <x-avatar :src="filled($member->avatar_path) ? route('internal.users.avatar', $member) : null" :name="$member->name" size="size-7" class="border-2 border-white dark:border-slate-900" />
                            @endforeach
                        </span>
                    </a>
                @empty
                    <x-empty-state icon="projects" title="No projects yet" description="This will be the first project in the workspace." class="p-8" />
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="new-project-title">
            <h2 id="new-project-title" class="text-xl font-bold">Start a new project</h2>
            <p class="mt-1 text-sm text-slate-500">You become its leader, and can add more members later.</p>

            <form method="POST" action="{{ route('internal.projects.store', $workspace) }}" class="mt-6 space-y-5" x-data="{ ai: {{ old('generate_with_ai', true) ? 'true' : 'false' }} }">
                @csrf
                <div><x-label for="name">Project name</x-label><x-input id="name" name="name" value="{{ old('name') }}" required autofocus /><x-field-error name="name" /></div>
                <div><x-label for="description">Description</x-label><x-textarea id="description" name="description" rows="5" maxlength="5000" x-bind:required="ai" x-bind:minlength="ai ? 80 : null" placeholder="Goal, scope, expected result, constraints, and important context…">{{ old('description') }}</x-textarea><p class="mt-1 text-xs text-slate-500">When AI drafting is enabled, provide at least 80 characters so it can produce useful tasks and checklists.</p><x-field-error name="description" /></div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><x-label for="status">Status</x-label><x-select id="status" name="status">@foreach (['planned' => 'Planned', 'active' => 'Active', 'on_hold' => 'On hold', 'completed' => 'Completed'] as $value => $label)<option value="{{ $value }}" @selected(old('status', 'planned') === $value)>{{ $label }}</option>@endforeach</x-select></div>
                    <div><x-label for="color">Color</x-label><x-input id="color" type="color" name="color" value="{{ old('color', '#4f46e5') }}" /></div>
                    <div><x-label for="start_date">Start date</x-label><x-input id="start_date" type="date" name="start_date" value="{{ old('start_date') }}" /></div>
                    <div><x-label for="due_date">Due date</x-label><x-input id="due_date" type="date" name="due_date" value="{{ old('due_date') }}" /><x-field-error name="due_date" /></div>
                </div>
                @if ($availableMembers->isNotEmpty())
                    <fieldset><legend class="text-sm font-semibold">Initial members</legend><div class="mt-3 grid gap-2 sm:grid-cols-2">@foreach ($availableMembers as $membership)<label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-800"><input type="checkbox" name="member_public_ids[]" value="{{ $membership->user->public_id }}" class="rounded border-slate-300 text-orbit-600 focus:ring-orbit-500" @checked(in_array($membership->user->public_id, old('member_public_ids', [])))><span>{{ $membership->user->name }}</span></label>@endforeach</div></fieldset>
                @endif
                <label class="flex items-start gap-3 rounded-2xl border border-orbit-200 bg-orbit-50/60 p-4 dark:border-orbit-900/70 dark:bg-orbit-950/30">
                    <input type="checkbox" name="generate_with_ai" value="1" x-model="ai" class="mt-0.5 rounded border-slate-300 text-orbit-600 focus:ring-orbit-500" @checked(old('generate_with_ai', true))>
                    <span><span class="block text-sm font-bold">Draft tasks with AI</span><span class="mt-1 block text-xs leading-5 text-slate-500">AI proposes tasks and to-do checklists after creation. Nothing reaches the board until an IT team member reviews and accepts the plan.</span></span>
                </label>
                <div class="flex gap-3"><x-button>Create project</x-button><x-link-button href="{{ route('app.projects.index', $workspace) }}" variant="secondary">Cancel</x-link-button></div>
            </form>
        </section>
    </div>
@endsection
