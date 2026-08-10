@extends('layouts.app')

@section('title', 'Workspace invitation')
@section('page-title', 'Workspace invitation')

@section('content')
    <div class="mx-auto max-w-xl rounded-3xl border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900">
        <div class="mx-auto grid size-14 place-items-center rounded-2xl bg-orbit-50 text-2xl text-orbit-700 dark:bg-orbit-950/60 dark:text-orbit-300">◇</div>
        <h2 class="mt-5 text-2xl font-bold">Join {{ $invitation->workspace->name }}</h2>
        <p class="mt-3 text-sm leading-6 text-slate-500">{{ $invitation->inviter?->name ?? 'A workspace owner' }} invited you as {{ $invitation->role->label() }}. The invitation expires {{ $invitation->expires_at->diffForHumans() }}.</p>
        @if ($invitation->accepted_at)
            <x-alert variant="success" :dismissible="false" class="mt-6 text-left">This invitation was already accepted.</x-alert>
        @elseif ($invitation->rejected_at)
            <p class="mt-6 rounded-xl bg-slate-100 p-4 text-sm text-slate-700">This invitation was declined.</p>
        @elseif ($invitation->expires_at->isPast())
            <x-alert variant="warning" :dismissible="false" class="mt-6 text-left">This invitation has expired. Ask an admin for a new one.</x-alert>
        @else
            <div class="mt-7 flex justify-center gap-3">
                <form method="POST" action="{{ route('internal.invitations.accept', $token) }}">@csrf<x-button>Accept invitation</x-button></form>
                <form method="POST" action="{{ route('internal.invitations.reject', $token) }}">@csrf<x-button variant="secondary">Decline</x-button></form>
            </div>
        @endif
    </div>
@endsection
