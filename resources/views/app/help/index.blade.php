@extends('layouts.app')

@section('title', 'Help center')
@section('page-title', 'Help center')

@section('content')
    <div>
        <section class="overflow-hidden rounded-3xl bg-slate-950 px-6 py-10 text-white shadow-xl sm:px-10 sm:py-14 dark:bg-gradient-to-br dark:from-orbit-950 dark:to-slate-950">
            <div class="max-w-2xl"><p class="text-sm font-semibold text-orbit-300">Elara knowledge base</p><h2 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">How can we help your team move forward?</h2><p class="mt-3 text-sm text-slate-300">Search practical guidance for projects, security, collaboration, and connected tools.</p></div>
            <form method="GET" action="{{ route('help') }}" class="mt-7 flex max-w-2xl flex-col gap-3 sm:flex-row"><label class="sr-only" for="help_search">Search help</label><input id="help_search" name="q" value="{{ $query }}" minlength="2" class="min-h-12 flex-1 rounded-xl border-white/10 bg-white/10 text-white placeholder:text-slate-400 focus:border-orbit-400 focus:ring-orbit-400" placeholder="Search articles…"><x-button class="bg-white text-slate-950 hover:bg-slate-100">Search</x-button></form>
        </section>

        <div class="mt-7 grid gap-6 lg:grid-cols-[1fr_360px]">
            <div>
                <div class="flex items-end justify-between gap-4"><div><p class="text-sm font-semibold text-orbit-600">Guides</p><h2 class="mt-1 text-2xl font-bold">{{ mb_strlen($query) >= 2 ? 'Search results' : 'Browse every topic' }}</h2></div><span class="text-sm text-slate-500">{{ $articles->total() }} articles</span></div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    @forelse($articles as $article)<a href="{{ route('help.articles.show', $article->slug) }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-orbit-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900"><span class="text-xs font-bold uppercase tracking-wide text-orbit-600">{{ $article->category }}</span><h3 class="mt-2 font-bold group-hover:text-orbit-700 dark:group-hover:text-orbit-300">{{ $article->title }}</h3><p class="mt-2 text-sm leading-6 text-slate-500">{{ str($article->body)->squish()->limit(120) }}</p><span class="mt-4 inline-flex text-sm font-semibold text-orbit-700 dark:text-orbit-300">Read guide →</span></a>@empty<div class="sm:col-span-2 rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700">No published article matches “{{ $query }}”. Try a broader term.</div>@endforelse
                </div>
                <div class="mt-5">{{ $articles->links() }}</div>
            </div>

            <aside class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-sm font-semibold text-orbit-600">Frequently asked</p><div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">@forelse($faqs as $faq)<a href="{{ route('help.articles.show', $faq->slug) }}" class="flex items-center justify-between gap-3 py-3 text-sm font-semibold hover:text-orbit-700"><span>{{ $faq->title }}</span><span>→</span></a>@empty<p class="py-3 text-sm text-slate-500">FAQ articles are being prepared.</p>@endforelse</div></section>

                @if($activeWorkspace)
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-sm font-semibold text-orbit-600">Still need help?</p><h3 class="mt-1 text-xl font-bold">Contact support</h3><p class="mt-2 text-sm text-slate-500">Tell us what happened and include the result you expected.</p><form method="POST" action="{{ route('internal.support-tickets.store', $activeWorkspace) }}" class="mt-5 space-y-4">@csrf<div><x-label for="support_subject">Subject</x-label><x-input id="support_subject" name="subject" value="{{ old('subject') }}" required /><x-field-error name="subject" /></div><div><x-label for="support_body">Details</x-label><x-textarea id="support_body" name="body" rows="5" required>{{ old('body') }}</x-textarea><x-field-error name="body" /></div><x-button class="w-full">Submit support request</x-button></form>@if($tickets->isNotEmpty())<div class="mt-6 border-t border-slate-100 pt-5 dark:border-slate-800"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Your recent requests</p><div class="mt-3 space-y-3">@foreach($tickets as $ticket)<div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800"><div class="flex justify-between gap-3"><strong class="truncate text-sm">{{ $ticket->subject }}</strong><span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-slate-600 dark:bg-slate-900 dark:text-slate-300">{{ $ticket->status->name }}</span></div><p class="mt-1 text-xs text-slate-500">{{ $ticket->created_at->diffForHumans() }}</p></div>@endforeach</div></div>@endif</section>
                @endif
            </aside>
        </div>
    </div>
@endsection
