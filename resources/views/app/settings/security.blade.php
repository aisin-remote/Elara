@extends('layouts.app')

@section('title', 'Security settings')
@section('page-title', 'Settings')

@section('content')
    <div>
        @include('app.settings._navigation')

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold text-orbit-600">Password</p>
                @if (auth()->user()->isOrganizationManaged())
                    <h2 class="mt-1 text-xl font-bold">Managed by your company</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Orbitra verifies your company-directory password at sign-in. Change or recover it through your company account service.</p>
                @else
                    <h2 class="mt-1 text-xl font-bold">Change your password</h2>
                    <p class="mt-2 text-sm text-slate-500">Use at least 12 characters with upper and lowercase letters, a number, and a symbol.</p>
                    <form method="POST" action="{{ route('internal.settings.password.update') }}" class="mt-6 space-y-4">
                        @csrf @method('PUT')
                        <div><x-label for="password_current">Current password</x-label><x-input id="password_current" name="current_password" type="password" autocomplete="current-password" required /><x-field-error name="current_password" /></div>
                        <div><x-label for="password">New password</x-label><x-input id="password" name="password" type="password" autocomplete="new-password" required /><x-field-error name="password" /></div>
                        <div><x-label for="password_confirmation">Confirm new password</x-label><x-input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required /></div>
                        <x-button>Update password</x-button>
                    </form>
                @endif
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-4"><div><p class="text-sm font-semibold text-orbit-600">Two-factor authentication</p><h2 class="mt-1 text-xl font-bold">Protect your sign-in</h2></div><span class="rounded-full px-3 py-1 text-xs font-bold {{ auth()->user()->two_factor_confirmed_at ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ auth()->user()->two_factor_confirmed_at ? 'Enabled' : 'Not enabled' }}</span></div>

                @if (! auth()->user()->two_factor_secret)
                    <p class="mt-3 text-sm text-slate-500">Add a rotating code from any TOTP authenticator app.</p>
                    <form method="POST" action="{{ route('internal.security.two-factor.enable') }}" class="mt-5 space-y-4">@csrf<div><x-label for="two_factor_password">Current password</x-label><x-input id="two_factor_password" name="current_password" type="password" required /><x-field-error name="current_password" /></div><x-button>Start setup</x-button></form>
                @elseif (! auth()->user()->two_factor_confirmed_at)
                    <div class="mt-5 grid gap-5 sm:grid-cols-[220px_1fr] sm:items-center">
                        <img src="{{ $qrDataUri }}" alt="Authenticator QR code" class="rounded-2xl border border-slate-200 bg-white p-2">
                        <div><p class="text-sm text-slate-500">Scan the QR code, then enter the six-digit code to finish setup.</p><p class="mt-3 break-all rounded-xl bg-slate-50 p-3 font-mono text-xs dark:bg-slate-800">{{ auth()->user()->two_factor_secret }}</p></div>
                    </div>
                    <form method="POST" action="{{ route('internal.security.two-factor.confirm') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">@csrf<div class="flex-1"><x-label for="confirm_code">Authenticator code</x-label><x-input id="confirm_code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required /><x-field-error name="code" /></div><x-button>Confirm setup</x-button></form>
                @else
                    <p class="mt-3 text-sm text-slate-500">Your account requires an authenticator or one-time recovery code after password verification.</p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <form method="POST" action="{{ route('internal.security.recovery-codes') }}" class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">@csrf<p class="text-sm font-semibold">New recovery codes</p><div class="mt-3"><x-label for="recovery_password">Current password</x-label><x-input id="recovery_password" name="current_password" type="password" required /></div><x-button class="mt-3" variant="secondary">Regenerate</x-button></form>
                        <form method="POST" action="{{ route('internal.security.two-factor.disable') }}" class="rounded-2xl bg-rose-50 p-4 dark:bg-rose-950/20">@csrf @method('DELETE')<p class="text-sm font-semibold text-rose-700 dark:text-rose-300">Disable 2FA</p><div class="mt-3"><x-label for="disable_password">Current password</x-label><x-input id="disable_password" name="current_password" type="password" required /></div><div class="mt-3"><x-label for="disable_code">Authenticator or recovery code</x-label><x-input id="disable_code" name="code" required /></div><x-button class="mt-3" variant="danger">Disable</x-button></form>
                    </div>
                @endif

                @if ($recoveryCodes)
                    <x-alert variant="warning" title="Save these recovery codes now" :dismissible="false" class="mt-5 max-w-none">
                        Each code works once and will not be displayed again.
                        <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm text-slate-900 dark:text-slate-100">@foreach ($recoveryCodes as $code)<span>{{ $code }}</span>@endforeach</div>
                    </x-alert>
                @endif
            </section>
        </div>

        <section class="mt-6 rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 border-b border-slate-200 p-6 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"><div><h2 class="text-xl font-bold">Active sessions</h2><p class="mt-1 text-sm text-slate-500">Review devices signed in to your account.</p></div>@if($sessions->count() > 1)<form method="POST" action="{{ route('internal.security.sessions.others') }}" class="flex flex-col gap-2 sm:flex-row">@csrf @method('DELETE')<x-input name="current_password" type="password" placeholder="Current password" required /><x-button variant="secondary">Sign out others</x-button></form>@endif</div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($sessions as $session)
                    <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0"><div class="flex items-center gap-2"><strong class="text-sm">{{ str($session->user_agent ?: 'Unknown device')->limit(82) }}</strong>@if(hash_equals($currentSessionId, $session->id))<span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-700">Current</span>@endif</div><p class="mt-1 text-xs text-slate-500">{{ $session->ip_address ?: 'Unknown IP' }} · Active {{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}</p></div>@unless(hash_equals($currentSessionId, $session->id))<form method="POST" action="{{ route('internal.security.sessions.destroy', $session->id) }}" class="flex flex-col gap-2 sm:flex-row">@csrf @method('DELETE')<x-input name="current_password" type="password" placeholder="Current password" required /><x-button variant="secondary">Sign out</x-button></form>@endunless</div>
                @empty
                    <p class="p-6 text-sm text-slate-500">Session listing is available when the database session driver is enabled.</p>
                @endforelse
            </div>
        </section>

        <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="text-xl font-bold">Security activity</h2><div class="mt-5 space-y-4">@forelse($securityEvents as $event)<div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4 last:border-0 last:pb-0 dark:border-slate-800"><div><p class="text-sm font-semibold">{{ str($event->event)->replace('.', ' ')->headline() }}</p><p class="mt-1 text-xs text-slate-500">{{ $event->ip_address ?: 'Unknown IP' }} · {{ str($event->user_agent ?: 'Unknown device')->limit(72) }}</p></div><time class="whitespace-nowrap text-xs text-slate-400">{{ $event->created_at->diffForHumans() }}</time></div>@empty<p class="text-sm text-slate-500">Security events will appear after account activity.</p>@endforelse</div></section>
    </div>
@endsection
