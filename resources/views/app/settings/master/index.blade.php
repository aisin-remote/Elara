@extends('layouts.app')

@section('title', 'Master data')
@section('page-title', 'Settings')
@section('master-title', 'Reference data')

@section('content')
    @include('app.settings._navigation')
    @include('app.settings.master._navigation')

    @php
        $masters = [
            ['app.settings.master.systems', 'Systems', 'The catalog feature requests are raised against.', $counts['systems'], 'projects', true],
            ['app.settings.master.categories', 'Task categories', 'Labels tasks are grouped by across every project.', $counts['categories'], 'list', true],
            ['app.settings.master.status-templates', 'Status template', 'The starting status set copied into every new project.', $counts['statuses'], 'board', true],
            ['app.settings.master.articles', 'Help articles', 'Knowledge base content served by the help centre.', $counts['articles'], 'help', true],
            [null, 'Member capacity', 'Working hours, working days, and leave used to schedule work.', null, 'team', false],
            [null, 'Holidays', 'Non-working dates skipped when a slot is picked.', null, 'calendar', false],
            [null, 'Request rules', 'Validation window, PIC grace period, scheduling horizon.', null, 'settings', false],
        ];
    @endphp

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($masters as [$routeName, $label, $description, $count, $icon, $available])
            @if ($available)
                <a href="{{ route($routeName, $workspace) }}" class="group rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
                    <div class="flex items-start justify-between gap-3">
                        <span class="grid size-10 place-items-center rounded-xl bg-orbit-50 text-orbit-700 dark:bg-orbit-950 dark:text-orbit-300"><x-icon :name="$icon" /></span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $count }}</span>
                    </div>
                    <h3 class="mt-4 font-bold">{{ $label }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
                </a>
            @else
                <div class="rounded-2xl border border-dashed border-slate-300 p-5 dark:border-slate-700">
                    <div class="flex items-start justify-between gap-3">
                        <span class="grid size-10 place-items-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800"><x-icon :name="$icon" /></span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-500 dark:bg-slate-800">Later phase</span>
                    </div>
                    <h3 class="mt-4 font-bold text-slate-500">{{ $label }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
                </div>
            @endif
        @endforeach
    </div>
@endsection
