@extends('layouts.app')

@section('title', 'Messages')
@section('page-title', 'Messages')

@section('content')
    <section
        data-messages-app
        data-workspace="{{ $workspace->public_id }}"
        data-current-user="{{ auth()->user()->public_id }}"
        data-conversations-url="{{ route('internal.conversations.index', $workspace) }}"
        data-create-url="{{ route('internal.conversations.store', $workspace) }}"
        data-initial-conversation="{{ request('conversation') }}"
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
        <div class="grid min-h-[calc(100vh-9rem)] lg:grid-cols-[310px_minmax(0,1fr)] xl:grid-cols-[310px_minmax(0,1fr)_250px]">
            <aside data-conversation-panel class="border-r border-slate-200 dark:border-slate-800">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-white">Conversations</h2>
                        <p class="text-xs text-slate-500">Direct, group, and project chats</p>
                    </div>
                    @can('create', [App\Models\Conversation::class, $workspace])
                        <button data-new-conversation type="button" class="grid size-10 place-items-center rounded-xl bg-orbit-600 text-xl text-white shadow-sm hover:bg-orbit-700" aria-label="New conversation">+</button>
                    @endcan
                </div>
                <div class="p-3">
                    <label class="relative block">
                        <span class="sr-only">Search conversations</span>
                        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input data-conversation-search type="search" class="h-10 w-full rounded-xl border-slate-200 bg-slate-50 pl-9 pr-3 text-sm dark:border-slate-700 dark:bg-slate-950" placeholder="Search messages…">
                    </label>
                </div>
                <div data-conversation-list class="max-h-[calc(100vh-15.5rem)] overflow-y-auto px-2 pb-3" aria-live="polite" aria-busy="true">
                    <div class="space-y-3 p-3"><x-skeleton class="h-16" label="Loading conversations" /><x-skeleton class="h-16" label="Loading conversations" /><x-skeleton class="h-16" label="Loading conversations" /></div>
                </div>
            </aside>

            <div data-thread-panel class="hidden min-w-0 flex-col lg:flex">
                <div data-empty-thread class="grid flex-1 place-items-center p-8 text-center">
                    <div>
                        <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-orbit-50 text-3xl dark:bg-orbit-950/50">💬</div>
                        <h2 class="mt-4 text-lg font-bold">Select a conversation</h2>
                        <p class="mt-1 max-w-sm text-sm text-slate-500">Choose a teammate or project conversation to start collaborating.</p>
                    </div>
                </div>

                <div data-active-thread class="hidden min-h-0 flex-1 flex-col">
                    <header class="flex min-h-16 items-center justify-between border-b border-slate-200 px-4 dark:border-slate-800">
                        <div class="flex min-w-0 items-center gap-3">
                            <button data-back-conversations type="button" class="rounded-lg p-2 text-slate-500 lg:hidden" aria-label="Back to conversations">←</button>
                            <div data-thread-avatar class="grid size-10 shrink-0 place-items-center rounded-xl bg-orbit-100 font-bold text-orbit-700 dark:bg-orbit-950 dark:text-orbit-300">O</div>
                            <div class="min-w-0">
                                <h2 data-thread-title class="truncate font-bold text-slate-900 dark:text-white"></h2>
                                <p data-thread-presence class="truncate text-xs text-slate-500">Loading…</p>
                            </div>
                        </div>
                        <a href="{{ route('app.schedule.index', $workspace) }}" class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-600 hover:border-orbit-300 hover:text-orbit-700 dark:border-slate-700 dark:text-slate-300">Schedule call</a>
                    </header>

                    <div data-load-older-wrap class="hidden border-b border-slate-100 py-2 text-center dark:border-slate-800">
                        <button data-load-older type="button" class="text-xs font-semibold text-orbit-700 hover:underline dark:text-orbit-300">Load older messages</button>
                    </div>
                    <div data-message-list class="min-h-0 flex-1 space-y-5 overflow-y-auto p-4 md:p-6" aria-live="polite"></div>
                    <div data-typing class="min-h-6 px-5 text-xs text-slate-500"></div>

                    @can('create', [App\Models\Conversation::class, $workspace])
                        <form data-message-form class="border-t border-slate-200 p-3 md:p-4 dark:border-slate-800">
                            <div data-attachment-preview class="hidden pb-2 text-xs text-slate-500"></div>
                            <div class="flex items-end gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-2 focus-within:border-orbit-400 dark:border-slate-700 dark:bg-slate-950">
                                <label class="grid size-10 shrink-0 cursor-pointer place-items-center rounded-xl text-lg text-slate-500 hover:bg-white dark:hover:bg-slate-800" title="Attach file">
                                    <span aria-hidden="true">📎</span><span class="sr-only">Attach files</span>
                                    <input data-message-files type="file" multiple class="sr-only" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
                                </label>
                                <textarea data-message-body rows="1" maxlength="10000" class="max-h-32 min-h-10 flex-1 resize-none border-0 bg-transparent px-1 py-2 text-sm focus:ring-0" placeholder="Write a message…"></textarea>
                                <div class="relative">
                                    <button data-emoji-toggle type="button" class="grid size-10 place-items-center rounded-xl text-lg text-slate-500 hover:bg-white dark:hover:bg-slate-800" aria-label="Add emoji">☺</button>
                                    <div data-emoji-menu class="absolute bottom-12 right-0 hidden w-44 rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                                        @foreach (['😀', '👍', '🎉', '❤️', '🔥', '✅', '👀', '🙏'] as $emoji)
                                            <button type="button" data-insert-emoji="{{ $emoji }}" class="size-9 rounded-lg text-lg hover:bg-slate-100 dark:hover:bg-slate-800">{{ $emoji }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <button type="submit" class="grid size-10 shrink-0 place-items-center rounded-xl bg-orbit-600 font-bold text-white hover:bg-orbit-700" aria-label="Send message">↑</button>
                            </div>
                        </form>
                    @endcan
                </div>
            </div>

            <aside data-details-panel class="hidden border-l border-slate-200 p-5 dark:border-slate-800 xl:block">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Conversation details</p>
                <div data-details-empty class="mt-6 text-sm text-slate-500">Select a conversation to see its members.</div>
                <div data-details-content class="hidden">
                    <div data-details-avatar class="mt-6 grid size-14 place-items-center rounded-2xl bg-orbit-100 text-xl font-bold text-orbit-700 dark:bg-orbit-950 dark:text-orbit-300">O</div>
                    <h3 data-details-title class="mt-3 font-bold"></h3>
                    <p data-details-type class="mt-1 text-xs capitalize text-slate-500"></p>
                    <p class="mt-7 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Members</p>
                    <div data-details-members class="mt-3 space-y-3"></div>
                </div>
            </aside>
        </div>

        <dialog data-conversation-dialog class="w-[min(92vw,520px)] rounded-2xl bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-white">
            <form data-conversation-form class="p-6">
                <div class="flex items-start justify-between">
                    <div><h2 class="text-lg font-bold">New conversation</h2><p class="mt-1 text-sm text-slate-500">Choose the right space for your discussion.</p></div>
                    <button data-close-conversation type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">✕</button>
                </div>
                <label class="mt-5 block text-sm font-semibold">Type
                    <select data-conversation-type name="type" class="mt-2 block w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                        <option value="direct">Direct message</option><option value="group">Group</option><option value="project">Project</option>
                    </select>
                </label>
                <label data-title-field class="mt-4 hidden text-sm font-semibold">Group name
                    <input name="title" maxlength="160" class="mt-2 block w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950" placeholder="e.g. Launch squad">
                </label>
                <label data-project-field class="mt-4 hidden text-sm font-semibold">Project
                    <select name="project_public_id" class="mt-2 block w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                        <option value="">Choose project</option>
                        @foreach ($projects as $project)<option value="{{ $project->public_id }}">{{ $project->name }}</option>@endforeach
                    </select>
                </label>
                <fieldset data-participant-field class="mt-4">
                    <legend class="text-sm font-semibold">People</legend>
                    <div class="mt-2 max-h-56 space-y-1 overflow-y-auto rounded-xl border border-slate-200 p-2 dark:border-slate-700">
                        @foreach ($members->where('id', '!=', auth()->id()) as $member)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800">
                                <input type="checkbox" name="participant_public_ids[]" value="{{ $member->public_id }}" class="rounded border-slate-300 text-orbit-600 focus:ring-orbit-500">
                                <span class="grid size-8 place-items-center rounded-lg bg-slate-100 text-xs font-bold dark:bg-slate-800">{{ strtoupper(substr($member->first_name, 0, 1).substr($member->last_name, 0, 1)) }}</span>
                                <span class="text-sm"><strong class="block">{{ $member->name }}</strong><span class="text-xs text-slate-500">{{ $member->email }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <p data-conversation-error class="mt-3 hidden text-sm font-semibold text-rose-600"></p>
                <div class="mt-6 flex justify-end gap-2">
                    <x-button data-close-conversation type="button" variant="secondary">Cancel</x-button>
                    <x-button type="submit">Create conversation</x-button>
                </div>
            </form>
        </dialog>
    </section>
@endsection
