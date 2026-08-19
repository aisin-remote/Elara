@extends('layouts.requester')

@section('title', 'Propose a project')
@section('page-title', 'Propose a project')

@section('content')
    <div class="border-b border-slate-200 pb-6 dark:border-slate-800">
        <h2 class="text-2xl font-bold tracking-tight">Propose a new project</h2>
        <p class="mt-2 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
            Build the business case below so your department and ITD can assess the problem, intended outcome, benefits, costs, and return.
        </p>
    </div>

    <x-auth-errors class="mt-6" />

    <x-link-button href="{{ asset('docs/elara-project-request-guide.pdf') }}" variant="secondary" download class="mt-6">
        <x-icon name="download" />
        Download Guide
    </x-link-button>

    <form method="POST" action="{{ route('desk.project-requests.store', $workspace) }}" class="mt-6 space-y-6">
        @csrf
        @include('desk.project-requests._form-fields')
        <div class="flex flex-wrap gap-3">
            <x-button>Submit project proposal</x-button>
            <x-link-button href="{{ route('desk.index') }}" variant="secondary">Cancel</x-link-button>
        </div>
    </form>
@endsection
