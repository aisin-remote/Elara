@extends('layouts.app')

@section('title', 'Notification settings')
@section('page-title', 'Notification settings')

@section('content')
    <div data-notification-settings data-endpoint="{{ route('internal.notification-preferences.update') }}" data-workspace="{{ $workspace->public_id }}" data-vapid-key="{{ $vapidPublicKey }}">
        @include('app.settings._navigation')
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <p class="text-sm text-slate-500">Control how Orbitra keeps you informed in {{ $workspace->name }}.</p>
                <h2 class="mt-1 text-xl font-bold">Choose what reaches you</h2>
            </div>
            <a href="{{ route('app.workspaces.settings', $workspace) }}" class="text-sm font-semibold text-orbit-700 dark:text-orbit-300">← Workspace settings</a>
        </div>

        <form data-preference-form class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid grid-cols-[minmax(0,1fr)_72px_72px_72px] items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-950/60">
                <span class="text-left">Event</span><span>Email</span><span>In-app</span><span>Push</span>
            </div>
            @foreach ($preferences as $event => $preference)
                <div data-preference-row="{{ $event }}" class="grid grid-cols-[minmax(0,1fr)_72px_72px_72px] items-center gap-2 border-b border-slate-100 px-4 py-4 last:border-0 dark:border-slate-800">
                    <div><strong class="text-sm">{{ $preference['label'] }}</strong><p class="mt-0.5 text-xs text-slate-500">Per-workspace preference</p></div>
                    @foreach (['mail', 'in_app', 'push'] as $channel)
                        <label class="mx-auto grid size-10 cursor-pointer place-items-center rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800"><span class="sr-only">{{ $preference['label'] }} via {{ $channel }}</span><input data-channel="{{ $channel }}" type="checkbox" class="rounded border-slate-300 text-orbit-600 focus:ring-orbit-500" @checked($preference[$channel])></label>
                    @endforeach
                </div>
            @endforeach
            <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-950/60">
                <p data-preference-status class="text-sm text-slate-500">Changes apply to this workspace.</p>
                <x-button type="submit">Save preferences</x-button>
            </div>
        </form>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div><h3 class="font-bold">Browser push notifications</h3><p class="mt-1 text-sm text-slate-500">Receive alerts even when Orbitra is in the background.</p></div>
                <x-button data-enable-push type="button" variant="secondary" :disabled="! (bool) $vapidPublicKey">Enable on this device</x-button>
            </div>
            @unless ($vapidPublicKey)
                <p class="mt-3 text-xs text-amber-700 dark:text-amber-300">Add VAPID keys to the environment before enabling browser push.</p>
            @endunless
        </div>
    </div>
@endsection
