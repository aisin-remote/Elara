@extends('layouts.app')

@section('title', 'Subscription')
@section('page-title', 'Settings')

@section('content')
    <div x-data="{ interval: 'monthly' }">
        @include('app.settings._navigation')

        @if (request('checkout') === 'success')
            <div hidden x-init="$nextTick(() => $dispatch('orbitra-toast', { message: 'Stripe will confirm the subscription through its signed webhook.', title: 'Checkout completed', variant: 'success' }))"></div>
        @elseif (request('checkout') === 'cancelled')
            <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">Checkout was cancelled. Your current plan has not changed.</div>
        @endif

        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div><p class="text-sm font-semibold text-orbit-600">Plans and billing</p><h2 class="mt-1 text-3xl font-bold tracking-tight">Choose the pace that fits your team</h2><p class="mt-2 max-w-2xl text-sm text-slate-500">Prices, limits, and Stripe price IDs are controlled by the server. New paid subscriptions include a {{ config('plans.trial_days') }}-day trial.</p></div>
            <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800"><button type="button" x-on:click="interval = 'monthly'" :class="interval === 'monthly' ? 'bg-white shadow-sm dark:bg-slate-700' : ''" class="rounded-lg px-4 py-2 text-sm font-semibold">Monthly</button><button type="button" x-on:click="interval = 'yearly'" :class="interval === 'yearly' ? 'bg-white shadow-sm dark:bg-slate-700' : ''" class="rounded-lg px-4 py-2 text-sm font-semibold">Yearly</button></div>
        </div>

        <div class="mt-7 grid gap-5 lg:grid-cols-3">
            @foreach ($plans as $key => $plan)
                @php($isCurrent = $currentPlan['key'] === $key)
                <article class="relative flex flex-col rounded-3xl border p-6 shadow-sm {{ $key === 'pro' ? 'border-orbit-400 bg-gradient-to-b from-orbit-50 to-white dark:from-orbit-950/60 dark:to-slate-900' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' }}">
                    @if ($key === 'pro')<span class="absolute right-5 top-5 rounded-full bg-orbit-600 px-3 py-1 text-xs font-bold text-white">Most popular</span>@endif
                    <div class="pr-20"><h3 class="text-xl font-bold">{{ $plan['name'] }}</h3><p class="mt-2 text-sm text-slate-500">{{ $plan['description'] }}</p></div>
                    <div class="mt-6 min-h-14">
                        @if ($plan['display_monthly'] === 0)<span class="text-4xl font-bold">Free</span>
                        @elseif ($plan['display_monthly'])<span class="text-4xl font-bold">${{ $plan['display_monthly'] }}</span><span class="text-sm text-slate-500"> / month</span><p x-show="interval === 'yearly'" class="mt-1 text-xs font-semibold text-emerald-600">Billed yearly using the configured Stripe price</p>
                        @else<span class="text-3xl font-bold">Let’s talk</span>@endif
                    </div>
                    <ul class="mt-6 flex-1 space-y-3 text-sm">@foreach($plan['features'] as $feature)<li class="flex gap-2"><span class="text-emerald-500">✓</span><span>{{ $feature }}</span></li>@endforeach</ul>
                    <div class="mt-7">
                        @if ($isCurrent)
                            <span class="flex min-h-11 items-center justify-center rounded-xl bg-slate-100 px-4 text-sm font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Current plan</span>
                        @elseif ($key === 'enterprise' && ! $plan['monthly'] && ! $plan['yearly'])
                            @if($contactSalesUrl)<a href="{{ $contactSalesUrl }}" class="flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white dark:bg-orbit-500 dark:text-slate-950">Contact sales</a>@else<span class="flex min-h-11 items-center justify-center rounded-xl bg-slate-100 px-4 text-sm font-semibold text-slate-500 dark:bg-slate-800">Sales contact not configured</span>@endif
                        @elseif ($key !== 'starter')
                            <form method="POST" action="{{ $subscription?->valid() ? route('internal.billing.change') : route('internal.billing.checkout') }}">
                                @csrf<input type="hidden" name="workspace_public_id" value="{{ $workspace->public_id }}"><input type="hidden" name="plan" value="{{ $key }}"><input type="hidden" name="interval" x-bind:value="interval">
                                <x-button class="w-full" x-bind:disabled="interval === 'monthly' ? {{ $plan['monthly'] ? 'false' : 'true' }} : {{ $plan['yearly'] ? 'false' : 'true' }}">{{ $subscription?->valid() ? 'Change to '.$plan['name'] : 'Start '.$plan['name'].' trial' }}</x-button>
                            </form>
                            @unless($plan['monthly'])<p class="mt-2 text-center text-xs text-amber-600">Configure Stripe price IDs to enable checkout.</p>@endunless
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if ($subscription)
            <section class="mt-7 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-sm font-semibold text-orbit-600">Current subscription</p><h3 class="mt-1 text-xl font-bold">{{ str($subscription->stripe_status)->replace('_', ' ')->headline() }}</h3><p class="mt-1 text-sm text-slate-500">Price {{ $subscription->stripe_price }}@if($subscription->ends_at) · Ends {{ $subscription->ends_at->toFormattedDateString() }}@endif</p></div><div class="flex flex-wrap gap-2"><form method="POST" action="{{ route('internal.billing.portal') }}">@csrf<input type="hidden" name="workspace_public_id" value="{{ $workspace->public_id }}"><x-button variant="secondary">Billing portal</x-button></form>@if($subscription->onGracePeriod())<form method="POST" action="{{ route('internal.billing.resume') }}">@csrf<input type="hidden" name="workspace_public_id" value="{{ $workspace->public_id }}"><x-button>Resume</x-button></form>@elseif($subscription->valid())<form method="POST" action="{{ route('internal.billing.cancel') }}">@csrf<input type="hidden" name="workspace_public_id" value="{{ $workspace->public_id }}"><x-button variant="danger">Cancel</x-button></form>@endif</div></div>
            </section>
        @endif

        <section class="mt-7 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-6 dark:border-slate-800"><h3 class="text-xl font-bold">Invoices</h3><p class="mt-1 text-sm text-slate-500">Receipts are loaded directly from your Stripe customer record.</p></div>
            @if($invoiceError)<p class="p-6 text-sm text-amber-700">{{ $invoiceError }}</p>@elseif(count($invoices))<div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-950/60"><tr><th class="px-6 py-3">Date</th><th class="px-6 py-3">Number</th><th class="px-6 py-3">Amount</th><th class="px-6 py-3">Status</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">@foreach($invoices as $invoice)<tr><td class="px-6 py-4">{{ $invoice->date()->toFormattedDateString() }}</td><td class="px-6 py-4 font-medium">{{ $invoice->number ?? $invoice->id }}</td><td class="px-6 py-4">{{ $invoice->total() }}</td><td class="px-6 py-4">{{ $invoice->paid ? 'Paid' : 'Open' }}</td></tr>@endforeach</tbody></table></div>@else<p class="p-6 text-sm text-slate-500">No Stripe invoices are available yet.</p>@endif
        </section>
    </div>
@endsection
