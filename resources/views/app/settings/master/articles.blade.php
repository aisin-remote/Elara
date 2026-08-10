@extends('layouts.app')

@section('title', 'Help articles')
@section('page-title', 'Settings')
@section('master-title', 'Help articles')

@section('content')
    @include('app.settings._navigation')
    @include('app.settings.master._navigation')

    <div class="grid gap-6 xl:grid-cols-[1fr_380px] xl:items-start">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="articles-title">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-5 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 id="articles-title" class="text-lg font-bold">{{ $articles->total() }} {{ \Illuminate\Support\Str::plural('article', $articles->total()) }}</h3>
                    <p class="mt-1 text-xs text-slate-500">Published articles appear in the help centre; drafts stay hidden.</p>
                </div>
                <form method="GET" class="flex gap-2">
                    <x-input name="search" value="{{ $search }}" placeholder="Search titles" aria-label="Search articles" class="sm:w-56" />
                    <x-button variant="secondary">Search</x-button>
                </form>
            </div>

            @forelse ($articles as $article)
                <div class="border-b border-slate-100 p-4 last:border-0 dark:border-slate-800" x-data="{ editing: false }">
                    <div class="flex flex-wrap items-start gap-3" x-show="! editing">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $article->title }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $article->category }} · /{{ $article->slug }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <x-badge :tone="$article->is_published ? 'success' : 'slate'">{{ $article->is_published ? 'Published' : 'Draft' }}</x-badge>
                            @if ($article->archived_at)<x-badge tone="slate">Archived</x-badge>@endif
                            <x-button type="button" variant="secondary" x-on:click="editing = true">Edit</x-button>
                            <form method="POST" action="{{ route('internal.master.articles.archive', $article) }}">
                                @csrf
                                <x-button variant="secondary">{{ $article->archived_at ? 'Restore' : 'Archive' }}</x-button>
                            </form>
                        </div>
                    </div>

                    <form x-cloak x-show="editing" method="POST" action="{{ route('internal.master.articles.update', $article) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><x-label for="title-{{ $article->public_id }}">Title</x-label><x-input id="title-{{ $article->public_id }}" name="title" value="{{ $article->title }}" required /></div>
                            <div><x-label for="category-{{ $article->public_id }}">Category</x-label><x-input id="category-{{ $article->public_id }}" name="category" value="{{ $article->category }}" required /></div>
                        </div>
                        <div><x-label for="slug-{{ $article->public_id }}">Slug</x-label><x-input id="slug-{{ $article->public_id }}" name="slug" value="{{ $article->slug }}" /></div>
                        <div><x-label for="body-{{ $article->public_id }}">Body</x-label><x-textarea id="body-{{ $article->public_id }}" name="body" rows="8" required>{{ $article->body }}</x-textarea></div>
                        <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="is_published" value="1" class="rounded border-slate-300 text-orbit-600 focus:ring-orbit-500" @checked($article->is_published)>Published</label>
                        <div class="flex gap-3"><x-button>Save article</x-button><x-button type="button" variant="secondary" x-on:click="editing = false">Cancel</x-button></div>
                    </form>
                </div>
            @empty
                <div class="p-5"><x-empty-state icon="help" title="No articles" description="Write the first help article on the right; it appears in the help centre once published." /></div>
            @endforelse

            @if ($articles->hasPages())
                <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $articles->links() }}</div>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="new-article-title">
            <h3 id="new-article-title" class="text-lg font-bold">New article</h3>
            <p class="mt-1 text-xs text-slate-500">Leave the slug blank and it is derived from the title.</p>
            <form method="POST" action="{{ route('internal.master.articles.store') }}" class="mt-4 space-y-4">
                @csrf
                <div><x-label for="new-title">Title</x-label><x-input id="new-title" name="title" required /><x-field-error name="title" /></div>
                <div><x-label for="new-category">Category</x-label><x-input id="new-category" name="category" placeholder="Getting started" required /><x-field-error name="category" /></div>
                <div><x-label for="new-slug">Slug</x-label><x-input id="new-slug" name="slug" /><x-field-error name="slug" /></div>
                <div><x-label for="new-body">Body</x-label><x-textarea id="new-body" name="body" rows="6" required></x-textarea><x-field-error name="body" /></div>
                <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="is_published" value="1" class="rounded border-slate-300 text-orbit-600 focus:ring-orbit-500">Publish immediately</label>
                <x-button class="w-full">Create article</x-button>
            </form>
        </section>
    </div>
@endsection
