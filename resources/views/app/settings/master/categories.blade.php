@extends('layouts.app')

@section('title', 'Task categories')
@section('page-title', 'Settings')
@section('master-title', 'Task categories')

@section('content')
    @include('app.settings._navigation')
    @include('app.settings.master._navigation')

    <div class="grid gap-6 xl:grid-cols-[1fr_360px] xl:items-start">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="categories-title">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-5 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 id="categories-title" class="text-lg font-bold">{{ $categories->count() }} {{ \Illuminate\Support\Str::plural('category', $categories->count()) }}</h3>
                    <p class="mt-1 text-xs text-slate-500">Archived categories stay readable on old tasks but leave every picker.</p>
                </div>
                <form method="GET" class="flex gap-2">
                    <x-input name="search" value="{{ $search }}" placeholder="Search categories" aria-label="Search categories" class="sm:w-56" />
                    <x-button variant="secondary">Search</x-button>
                </form>
            </div>

            @forelse ($categories as $category)
                <div class="border-b border-slate-100 p-4 last:border-0 dark:border-slate-800" x-data="{ editing: false }">
                    <div class="flex flex-wrap items-center gap-3" x-show="! editing">
                        <span class="size-4 shrink-0 rounded-full" style="background-color: {{ $category->color }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $category->name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $category->tasks_count }} {{ \Illuminate\Support\Str::plural('task', $category->tasks_count) }}
                                @if ($category->archived_at)
                                    · <span class="font-semibold text-amber-600 dark:text-amber-400">Archived</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            @if ($category->archived_at)
                                <form method="POST" action="{{ route('internal.master.categories.restore', $category) }}">@csrf<x-button variant="secondary">Restore</x-button></form>
                            @else
                                <x-button type="button" variant="secondary" x-on:click="editing = true">Edit</x-button>
                                <x-button type="button" variant="secondary" onclick="document.getElementById('archive-{{ $category->public_id }}').showModal()">Archive</x-button>
                            @endif
                        </div>
                    </div>

                    <form x-cloak x-show="editing" method="POST" action="{{ route('internal.master.categories.update', $category) }}" class="grid gap-3 sm:grid-cols-[1fr_120px_auto_auto]">
                        @csrf @method('PATCH')
                        <x-input name="name" value="{{ $category->name }}" required aria-label="Category name" />
                        <x-input type="color" name="color" value="{{ $category->color }}" aria-label="Category colour" />
                        <x-button>Save</x-button>
                        <x-button type="button" variant="secondary" x-on:click="editing = false">Cancel</x-button>
                    </form>

                    @unless ($category->archived_at)
                        <x-modal id="archive-{{ $category->public_id }}" title="Archive {{ $category->name }}?">
                            <form method="POST" action="{{ route('internal.master.categories.archive', $category) }}">
                                @csrf
                                <p class="text-sm leading-6 text-slate-500">
                                    @if ($category->tasks_count > 0)
                                        {{ $category->tasks_count }} {{ \Illuminate\Support\Str::plural('task', $category->tasks_count) }} still use this category. Move them somewhere else, or clear the category from them.
                                    @else
                                        Nothing uses this category yet, so nothing moves.
                                    @endif
                                </p>

                                @if ($category->tasks_count > 0)
                                    <div class="mt-4">
                                        <x-label for="replacement-{{ $category->public_id }}">Move those tasks to</x-label>
                                        <x-select id="replacement-{{ $category->public_id }}" name="replacement_public_id">
                                            <option value="">No category</option>
                                            @foreach ($categories->where('archived_at', null)->where('id', '!=', $category->id) as $option)
                                                <option value="{{ $option->public_id }}">{{ $option->name }}</option>
                                            @endforeach
                                        </x-select>
                                        <input type="hidden" name="clear_tasks" value="1">
                                    </div>
                                @endif

                                <div class="mt-6 flex justify-end gap-3">
                                    <x-button type="button" variant="secondary" onclick="this.closest('dialog').close()">Cancel</x-button>
                                    <x-button variant="danger">Archive category</x-button>
                                </div>
                            </form>
                        </x-modal>
                    @endunless
                </div>
            @empty
                <div class="p-5"><x-empty-state icon="list" title="No categories yet" description="Create one on the right; it becomes selectable on every task in this workspace." /></div>
            @endforelse
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="new-category-title">
            <h3 id="new-category-title" class="text-lg font-bold">Add a category</h3>
            <form method="POST" action="{{ route('internal.task-categories.store', $workspace) }}" class="mt-4 space-y-4">
                @csrf
                <div><x-label for="name">Name</x-label><x-input id="name" name="name" required /><x-field-error name="name" /></div>
                <div><x-label for="color">Colour</x-label><x-input id="color" type="color" name="color" value="#6366f1" /><x-field-error name="color" /></div>
                <x-button class="w-full">Add category</x-button>
            </form>
        </section>
    </div>
@endsection
