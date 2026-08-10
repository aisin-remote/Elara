@extends('layouts.app')

@section('title', 'Holidays')
@section('page-title', 'Settings')
@section('master-title', 'Holidays')

@section('content')
    @include('app.settings._navigation')
    @include('app.settings.master._navigation')

    <div class="grid gap-6 xl:grid-cols-[1fr_360px] xl:items-start">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="holidays-title">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <h3 id="holidays-title" class="text-lg font-bold">{{ $holidays->count() }} non-working {{ \Illuminate\Support\Str::plural('date', $holidays->count()) }}</h3>
                <p class="mt-1 text-xs text-slate-500">Skipped when the scheduler walks forward looking for a slot.</p>
            </div>

            @forelse ($holidays as $holiday)
                <div class="flex items-center gap-3 border-b border-slate-100 p-4 last:border-0 dark:border-slate-800">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $holiday->name }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $holiday->observed_on->format('l, M j, Y') }}</p>
                    </div>
                    <form method="POST" action="{{ route('internal.master.holidays.destroy', $holiday) }}">
                        @csrf @method('DELETE')
                        <x-button variant="secondary">Remove</x-button>
                    </form>
                </div>
            @empty
                <div class="p-5"><x-empty-state icon="calendar" title="No holidays recorded" description="Add the dates the team does not work, so the scheduler stops planning across them." /></div>
            @endforelse
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-lg font-bold">Add a holiday</h3>
            <form method="POST" action="{{ route('internal.master.holidays.store', $workspace) }}" class="mt-4 space-y-4">
                @csrf
                <div><x-label for="observed_on">Date</x-label><x-input id="observed_on" type="date" name="observed_on" required /><x-field-error name="observed_on" /></div>
                <div><x-label for="name">Name</x-label><x-input id="name" name="name" placeholder="Independence Day" required /><x-field-error name="name" /></div>
                <x-button class="w-full">Add holiday</x-button>
            </form>
        </section>
    </div>
@endsection
