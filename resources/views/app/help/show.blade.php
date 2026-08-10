@extends('layouts.app')

@section('title', $article->title)
@section('page-title', 'Help center')

@section('content')
    <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10 dark:border-slate-800 dark:bg-slate-900">
        <a href="{{ route('help') }}" class="text-sm font-semibold text-orbit-700 dark:text-orbit-300">← Back to help center</a>
        <p class="mt-8 text-xs font-bold uppercase tracking-[0.18em] text-orbit-600">{{ $article->category }}</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ $article->title }}</h1>
        <p class="mt-3 text-sm text-slate-500">Updated {{ $article->updated_at->toFormattedDateString() }}</p>
        <div class="mt-8 space-y-5 text-base leading-8 text-slate-700 dark:text-slate-300">@foreach(preg_split('/\R{2,}/', trim($article->body)) as $paragraph)<p>{{ $paragraph }}</p>@endforeach</div>
    </article>
@endsection
