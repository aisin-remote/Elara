@extends('layouts.app')

@section('title', 'New feature')
@section('page-title', 'New feature')

@section('content')
    @php($selectedSystem = $systems->firstWhere('public_id', old('system_public_id', request('system'))))

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="existing-features-title">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 id="existing-features-title" class="text-xl font-bold">Already in {{ $workspace->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $existingFeatures->count() }} recent {{ \Illuminate\Support\Str::plural('feature', $existingFeatures->count()) }}. Check here before starting a duplicate.</p>
                </div>
                <a href="{{ route('app.features.index', $workspace) }}" class="shrink-0 text-xs font-bold text-orbit-700 dark:text-orbit-300">Open list</a>
            </div>

            <div class="mt-6 space-y-3">
                @forelse ($existingFeatures as $existing)
                    <a href="{{ route('app.features.detail', [$workspace, $existing->project, $existing]) }}" class="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:hover:border-slate-700 dark:hover:bg-slate-800/60">
                        <span class="size-3 shrink-0 rounded-full" style="background: {{ $existing->project?->color ?? '#64748b' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $existing->name }}</span>
                            <span class="mt-0.5 block truncate text-xs text-slate-500">
                                {{ $existing->project?->name }}
                                @if ($existing->due_at)
                                    · due {{ $existing->due_at->format('M j, Y') }}
                                @endif
                            </span>
                        </span>
                    </a>
                @empty
                    <x-empty-state icon="board" title="No features yet" description="This will be the first feature in the workspace." class="p-8" />
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="new-feature-title">
            <h2 id="new-feature-title" class="text-xl font-bold">Add feature work</h2>
            <p class="mt-1 text-sm leading-6 text-slate-500">Create work directly for a system. This skips requester approval because it is entered by the IT team.</p>

            <form method="POST" action="{{ route('internal.features.store', $workspace) }}" class="mt-6 space-y-5" x-data="descriptionDraft({ endpoint: @js(route('internal.ai.descriptions.store', $workspace)), kind: 'feature', initialDescription: @js(old('description', '')), aiEnabled: @js((bool) old('generate_with_ai', true)) })">
                @csrf
                <x-form-errors />

                <div>
                    <x-label>System</x-label>
                    @if ($selectedSystem)
                        <input type="hidden" name="system_public_id" value="{{ $selectedSystem->public_id }}">
                        <div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold dark:border-slate-700 dark:bg-slate-800">{{ $selectedSystem->name }}</div>
                    @else
                        <x-select id="system_public_id" name="system_public_id" required autofocus>
                            <option value="">Choose a system</option>
                            @foreach ($systems as $system)
                                <option value="{{ $system->public_id }}">{{ $system->name }}</option>
                            @endforeach
                        </x-select>
                    @endif
                    <x-field-error name="system_public_id" />
                </div>

                <div>
                    <x-label for="name">Feature name</x-label>
                    <x-input id="name" name="name" value="{{ old('name') }}" maxlength="200" required />
                    <x-field-error name="name" />
                </div>

                <div>
                    <x-label for="description" class="mb-2 block">Description</x-label>
                    <div class="relative">
                        <x-textarea id="description" name="description" rows="7" maxlength="5000" required x-model="description" x-ref="description" x-bind:minlength="ai ? 80 : 20" placeholder="Write a short feature idea, then select Generate with AI to expand it…" class="pb-12">{{ old('description') }}</x-textarea>
                        <div class="absolute bottom-2 right-2">
                            <button type="button" x-on:click="generate" x-bind:disabled="generating" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-orbit-100 px-3 py-1.5 text-xs font-bold text-orbit-800 transition hover:bg-orbit-200 disabled:cursor-wait disabled:opacity-60 dark:bg-orbit-900/50 dark:text-orbit-200 dark:hover:bg-orbit-900">
                                <x-icon name="sparkles" class="size-4" />
                                <span x-text="generating ? 'Generating…' : 'Generate with AI'">Generate with AI</span>
                            </button>
                        </div>
                    </div>
                    <p x-show="generated" x-cloak class="mt-2 text-xs font-medium text-emerald-700 dark:text-emerald-300">AI expanded the description. Review and edit it before creating the feature.</p>
                    <p x-show="error" x-cloak x-text="error" class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-300" role="alert"></p>
                    <p class="mt-1 text-xs text-slate-500">A short brief is enough for Generate with AI. Keep at least 80 characters when task drafting is enabled.</p>
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
                    <x-link-button href="{{ $selectedSystem ? route('app.features.show', [$workspace, $selectedSystem]) : route('app.features.index', $workspace) }}" variant="secondary">Cancel</x-link-button>
                </div>
            </form>
        </section>
    </div>
@endsection
