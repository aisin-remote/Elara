@extends('layouts.app')

@section('title', 'Ask AI')
@section('page-title', 'Ask AI')

@section('content')
    <section
        data-ask-ai
        data-stream-url="{{ route('internal.ai.messages.store', $workspace) }}"
        data-new-chat-url="{{ route('app.ai.index', $workspace) }}"
        data-conversation="{{ $selectedConversation?->public_id }}"
        class="h-[calc(100dvh-6rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:h-[calc(100dvh-7.5rem)] dark:border-slate-800 dark:bg-slate-900"
    >
        <div class="grid h-full min-h-0 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="hidden min-h-0 border-r border-slate-200 bg-slate-50/70 lg:flex lg:flex-col dark:border-slate-800 dark:bg-slate-950/40">
                <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                    <a href="{{ route('app.ai.index', $workspace) }}" class="flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">
                        <span aria-hidden="true">＋</span> New chat
                    </a>
                </div>
                <nav data-ai-history class="min-h-0 flex-1 space-y-1 overflow-y-auto p-3" aria-label="Ask AI history">
                    @forelse ($conversations as $conversation)
                        <a
                            href="{{ route('app.ai.show', [$workspace, $conversation]) }}"
                            data-conversation-link="{{ $conversation->public_id }}"
                            @if ($selectedConversation?->is($conversation)) aria-current="page" @endif
                            class="block rounded-xl px-3 py-3 {{ $selectedConversation?->is($conversation) ? 'bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700' : 'hover:bg-white dark:hover:bg-slate-800' }}"
                        >
                            <span class="block truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $conversation->title }}</span>
                            <span class="mt-1 block truncate text-xs text-slate-500">{{ $conversation->project?->name ?? 'Workspace-wide' }} · {{ $conversation->updated_at->diffForHumans() }}</span>
                        </a>
                    @empty
                        <p data-ai-empty-history class="px-3 py-6 text-center text-sm text-slate-500">Your chats will appear here.</p>
                    @endforelse
                </nav>
                <div class="border-t border-slate-200 p-4 text-xs leading-5 text-slate-500 dark:border-slate-800">
                    Read-only in Phase A. Ask AI can inspect data you already have permission to see, but cannot change it.
                </div>
            </aside>

            <div class="flex min-h-0 min-w-0 flex-col">
                <header class="shrink-0 border-b border-slate-200 px-4 py-3 md:px-6 dark:border-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-sky-400 to-indigo-600 text-white shadow-sm">
                                <x-icon name="sparkles" />
                            </div>
                            <div class="min-w-0">
                                <h2 data-ai-title class="truncate font-bold text-slate-900 dark:text-white">{{ $selectedConversation?->title ?? 'Orbitra copilot' }}</h2>
                                <p class="truncate text-xs text-slate-500">{{ $model }} · workspace data stays permission-scoped</p>
                            </div>
                        </div>
                        @if ($selectedConversation?->project)
                            <span class="rounded-full bg-orbit-50 px-3 py-1.5 text-xs font-semibold text-orbit-700 dark:bg-orbit-950/60 dark:text-orbit-300">{{ $selectedConversation->project->name }}</span>
                        @elseif (! $selectedConversation)
                            <label data-ai-context class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                                Context
                                <select data-ai-project class="h-9 max-w-52 rounded-lg border-slate-200 bg-white py-1 pl-3 pr-8 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                    <option value="">All visible workspace data</option>
                                    @foreach ($projects as $project)
                                        <option value="{{ $project->public_id }}">{{ $project->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                    </div>

                    <details class="mt-3 lg:hidden">
                        <summary class="cursor-pointer text-xs font-semibold text-orbit-700 dark:text-orbit-300">Chat history</summary>
                        <div class="mt-2 max-h-48 space-y-1 overflow-y-auto rounded-xl bg-slate-50 p-2 dark:bg-slate-950">
                            <a href="{{ route('app.ai.index', $workspace) }}" class="block rounded-lg px-3 py-2 text-sm font-semibold">＋ New chat</a>
                            @foreach ($conversations as $conversation)
                                <a href="{{ route('app.ai.show', [$workspace, $conversation]) }}" class="block truncate rounded-lg px-3 py-2 text-sm hover:bg-white dark:hover:bg-slate-800">{{ $conversation->title }}</a>
                            @endforeach
                        </div>
                    </details>
                </header>

                <div data-ai-messages class="min-h-0 flex-1 overflow-y-auto px-4 py-6 md:px-8" aria-live="polite" aria-busy="false">
                    <div data-ai-empty class="{{ $messages->isNotEmpty() ? 'hidden' : 'grid' }} min-h-full place-items-center py-10 text-center">
                        <div class="w-full max-w-2xl">
                            <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-gradient-to-br from-sky-400 to-indigo-600 text-white shadow-lg shadow-indigo-500/20">
                                <x-icon name="sparkles" class="size-7" />
                            </div>
                            <h2 class="mt-5 text-2xl font-bold text-slate-950 dark:text-white">What can I help you move forward?</h2>
                            <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">Ask about your tasks, project health, schedules, team workload, or request status. You can also ask for a draft without changing any data.</p>
                            <div class="mt-7 grid gap-3 text-left sm:grid-cols-2">
                                @foreach ([
                                    'What are my most urgent tasks and why?',
                                    'Summarize the health of my active projects.',
                                    'Show this week’s schedule and possible conflicts.',
                                    'Draft a concise progress update for stakeholders.',
                                ] as $prompt)
                                    <button type="button" data-ai-suggestion="{{ $prompt }}" class="rounded-2xl border border-slate-200 p-4 text-sm font-medium leading-6 text-slate-700 transition hover:border-orbit-300 hover:bg-orbit-50/50 dark:border-slate-700 dark:text-slate-200 dark:hover:border-orbit-700 dark:hover:bg-orbit-950/30">{{ $prompt }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div data-ai-thread class="mx-auto max-w-3xl space-y-6">
                        @foreach ($messages as $message)
                            <article data-ai-message="{{ $message->role }}" class="flex gap-3 {{ $message->role === 'user' ? 'justify-end' : 'justify-start' }}">
                                @if ($message->role === 'assistant')
                                    <div class="mt-1 grid size-8 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-sky-400 to-indigo-600 text-white"><x-icon name="sparkles" class="size-4" /></div>
                                @endif
                                <div class="max-w-[88%]">
                                    @if ($message->role === 'assistant')
                                        <div data-ai-body data-ai-rendered class="break-words rounded-2xl rounded-bl-md bg-slate-100 px-4 py-3 text-sm leading-6 text-slate-800 [&_a]:font-semibold [&_a]:text-orbit-700 [&_a]:underline [&_a]:decoration-orbit-300 [&_a]:underline-offset-2 [&_li]:ml-5 [&_ol]:list-decimal [&_ol]:space-y-2 [&_p+p]:mt-3 [&_strong]:font-bold [&_ul]:list-disc [&_ul]:space-y-1 dark:bg-slate-800 dark:text-slate-100 dark:[&_a]:text-orbit-300">{!! Illuminate\Support\Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                                    @else
                                        <div data-ai-body class="whitespace-pre-wrap break-words rounded-2xl rounded-br-md bg-slate-900 px-4 py-3 text-sm leading-6 text-white dark:bg-slate-100 dark:text-slate-900">{{ $message->body }}</div>
                                    @endif
                                    @if ($message->role === 'assistant')
                                        <p class="mt-1 px-1 text-[11px] text-slate-400">{{ $message->model }}@if ($message->input_tokens || $message->output_tokens) · {{ number_format(($message->input_tokens ?? 0) + ($message->output_tokens ?? 0)) }} tokens @endif</p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>

                <form data-ai-form class="shrink-0 border-t border-slate-200 bg-white p-3 md:p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="mx-auto max-w-3xl">
                        <p data-ai-error class="mb-2 hidden rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-950/40 dark:text-rose-300" role="alert"></p>
                        <div class="flex items-end gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-2 shadow-sm focus-within:border-orbit-400 focus-within:ring-2 focus-within:ring-orbit-100 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:ring-orbit-950">
                            <textarea data-ai-input rows="1" maxlength="4000" class="max-h-36 min-h-11 flex-1 resize-none border-0 bg-transparent px-3 py-2.5 text-sm leading-6 focus:ring-0" placeholder="Ask about your Orbitra workspace…" aria-label="Message Ask AI"></textarea>
                            <button data-ai-submit type="submit" class="grid size-11 shrink-0 place-items-center rounded-xl bg-orbit-600 font-bold text-white hover:bg-orbit-700 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Send message">↑</button>
                        </div>
                        <p class="mt-2 text-center text-[11px] text-slate-400">Ask AI may make mistakes. Verify important details before acting.</p>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
