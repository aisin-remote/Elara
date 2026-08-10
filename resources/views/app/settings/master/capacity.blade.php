@extends('layouts.app')

@section('title', 'Member capacity')
@section('page-title', 'Settings')
@section('master-title', 'Member capacity')

@section('content')
    @include('app.settings._navigation')
    @include('app.settings.master._navigation')

    <x-alert variant="info" :dismissible="false" class="mb-6 max-w-none">
        Scheduling uses committed effort per working day, not calendar free/busy. The default is
        {{ $defaults['hours'] }} hours a day rather than eight — meetings, support, and slack live in
        the difference between a plan and a wish.
    </x-alert>

    <div class="grid gap-6 xl:grid-cols-[1fr_380px] xl:items-start">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="capacity-title">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <h3 id="capacity-title" class="text-lg font-bold">Working hours</h3>
                <p class="mt-1 text-xs text-slate-500">Anyone without a row here is planned at the default.</p>
            </div>

            @foreach ($members as $membership)
                @php($capacity = $capacities->get($membership->user_id))
                <form method="POST" action="{{ route('internal.master.capacity.save', $workspace) }}" class="border-b border-slate-100 p-4 last:border-0 dark:border-slate-800">
                    @csrf
                    <input type="hidden" name="user_public_id" value="{{ $membership->user->public_id }}">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-avatar :src="filled($membership->user->avatar_path) ? route('internal.users.avatar', $membership->user) : null" :name="$membership->user->name" size="size-9" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $membership->user->name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $capacity ? 'Custom' : 'Default' }}</p>
                        </div>
                        <label class="flex items-center gap-2 text-xs text-slate-500">
                            Hours/day
                            <x-input type="number" step="0.5" min="0.5" max="12" name="hours_per_day" value="{{ $capacity->hours_per_day ?? $defaults['hours'] }}" class="w-24" aria-label="Hours per day for {{ $membership->user->name }}" />
                        </label>
                        <x-button variant="secondary">Save</x-button>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-3 pl-12">
                        @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $iso => $label)
                            <label class="flex items-center gap-1.5 text-xs">
                                <input type="checkbox" name="working_days[]" value="{{ $iso }}" class="rounded border-slate-300 text-orbit-600 focus:ring-orbit-500"
                                    @checked(in_array($iso, $capacity?->workingDays() ?? $defaults['days'], true))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </form>
            @endforeach
        </section>

        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-lg font-bold">Record time off</h3>
                <p class="mt-1 text-xs text-slate-500">Blocks new scheduling. Work already assigned is flagged for a human, never moved silently.</p>
                <form method="POST" action="{{ route('internal.master.exceptions.store', $workspace) }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <x-label for="user_public_id">Who</x-label>
                        <x-select id="user_public_id" name="user_public_id" required>
                            @foreach ($members as $membership)
                                <option value="{{ $membership->user->public_id }}">{{ $membership->user->name }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><x-label for="starts_on">From</x-label><x-input id="starts_on" type="date" name="starts_on" required /></div>
                        <div><x-label for="ends_on">To</x-label><x-input id="ends_on" type="date" name="ends_on" required /></div>
                    </div>
                    <div>
                        <x-label for="reason">Reason</x-label>
                        <x-select id="reason" name="reason">
                            <option value="leave">Leave</option>
                            <option value="training">Training</option>
                            <option value="other">Other</option>
                        </x-select>
                    </div>
                    <x-button class="w-full">Record time off</x-button>
                </form>
            </section>

            @if ($exceptions->isNotEmpty())
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="border-b border-slate-200 p-5 text-lg font-bold dark:border-slate-800">Booked time off</h3>
                    @foreach ($exceptions as $exception)
                        <div class="flex items-center gap-3 border-b border-slate-100 p-4 last:border-0 dark:border-slate-800">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">{{ $exception->user->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $exception->starts_on->format('M j') }} – {{ $exception->ends_on->format('M j, Y') }} · {{ ucfirst($exception->reason) }}</p>
                            </div>
                            <form method="POST" action="{{ route('internal.master.exceptions.destroy', $exception) }}">
                                @csrf @method('DELETE')
                                <x-button variant="secondary">Remove</x-button>
                            </form>
                        </div>
                    @endforeach
                </section>
            @endif
        </div>
    </div>
@endsection
