@extends('layouts.app')

@section('title', 'Status template')
@section('page-title', 'Settings')
@section('master-title', 'Status template')

@section('content')
    @include('app.settings._navigation')
    @include('app.settings.master._navigation')

    @if ($usesFallback)
        <x-alert variant="info" class="mb-6 max-w-none">
            No template yet, so new projects start with the built-in set: Outstanding, In Progress, Pending, Done. Add a status below and the built-in set stops being used.
        </x-alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_360px] xl:items-start">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="templates-title">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <h3 id="templates-title" class="text-lg font-bold">Copied into every new project</h3>
                <p class="mt-1 text-xs text-slate-500">Projects that already exist keep their own statuses — this only changes what the next one starts with.</p>
            </div>

            @forelse ($templates as $template)
                <div class="border-b border-slate-100 p-4 last:border-0 dark:border-slate-800" x-data="{ editing: false }">
                    <div class="flex flex-wrap items-center gap-3" x-show="! editing">
                        <span class="size-4 shrink-0 rounded-full" style="background-color: {{ $template->color }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $template->name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $template->category->label() }}
                                @if ($template->archived_at)
                                    · <span class="font-semibold text-amber-600 dark:text-amber-400">Archived</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            @unless ($template->archived_at)
                                <x-button type="button" variant="secondary" x-on:click="editing = true">Edit</x-button>
                            @endunless
                            <form method="POST" action="{{ route('internal.master.status-templates.archive', $template) }}">
                                @csrf
                                <x-button variant="secondary">{{ $template->archived_at ? 'Restore' : 'Archive' }}</x-button>
                            </form>
                        </div>
                    </div>

                    <form x-cloak x-show="editing" method="POST" action="{{ route('internal.master.status-templates.update', $template) }}" class="grid gap-3 sm:grid-cols-[1fr_160px_120px_auto_auto]">
                        @csrf @method('PATCH')
                        <x-input name="name" value="{{ $template->name }}" required aria-label="Status name" />
                        <x-select name="category" aria-label="Status category">
                            @foreach ($categories as $category)
                                <option value="{{ $category->value }}" @selected($template->category === $category)>{{ $category->label() }}</option>
                            @endforeach
                        </x-select>
                        <x-input type="color" name="color" value="{{ $template->color }}" aria-label="Status colour" />
                        <x-button>Save</x-button>
                        <x-button type="button" variant="secondary" x-on:click="editing = false">Cancel</x-button>
                    </form>
                </div>
            @empty
                <div class="p-5"><x-empty-state icon="board" title="No template statuses" description="Add the statuses your projects normally start with; order follows the order you add them." /></div>
            @endforelse
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="new-template-title">
            <h3 id="new-template-title" class="text-lg font-bold">Add a status</h3>
            <form method="POST" action="{{ route('internal.master.status-templates.store', $workspace) }}" class="mt-4 space-y-4">
                @csrf
                <div><x-label for="name">Name</x-label><x-input id="name" name="name" required /><x-field-error name="name" /></div>
                <div>
                    <x-label for="category">Category</x-label>
                    <x-select id="category" name="category">
                        @foreach ($categories as $category)
                            <option value="{{ $category->value }}">{{ $category->label() }}</option>
                        @endforeach
                    </x-select>
                    <x-field-error name="category" />
                </div>
                <div><x-label for="color">Colour</x-label><x-input id="color" type="color" name="color" value="#6366f1" /><x-field-error name="color" /></div>
                <x-button class="w-full">Add status</x-button>
            </form>
        </section>
    </div>
@endsection
