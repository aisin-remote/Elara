@extends('layouts.app')

@section('title', 'Departments')
@section('page-title', 'Settings')
@section('master-title', 'Departments')

@section('content')
    @include('app.settings._navigation')
    @include('app.settings.master._navigation')

    <x-form-errors class="mb-4" />

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="departments-title">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-5 dark:border-slate-800 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 id="departments-title" class="text-lg font-bold">Department PIC</h3>
                <p class="mt-1 text-xs text-slate-500">Departments come live from PostgreSQL. Elara stores only the default IT PIC you choose.</p>
            </div>
            <form method="GET" class="flex gap-2">
                <x-input name="search" value="{{ $search }}" placeholder="Search departments" aria-label="Search departments" class="sm:w-56" />
                <x-button variant="secondary">Search</x-button>
            </form>
        </div>

        @if (! $directoryAvailable)
            <div class="p-5">
                <x-empty-state icon="alert" title="Organisation directory unavailable" description="Elara could not read the PostgreSQL department list. Existing PIC mappings have not been changed." />
            </div>
        @elseif ($departments->isEmpty())
            <div class="p-5">
                <x-empty-state icon="search" title="No departments match" description="Try a different department name or code." />
            </div>
        @else
            @php($candidateOptions = $candidates->map(fn ($candidate) => [
                'value' => $candidate->user->public_id,
                'label' => $candidate->user->name.($candidate->user->job_title ? ' — '.$candidate->user->job_title : ''),
            ])->values())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-slate-50/80 text-[11px] uppercase tracking-[.1em] text-slate-400 dark:bg-slate-900">
                        <tr>
                            <th class="px-5 py-3">Department</th>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Default PIC</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($departments as $department)
                            @php($mapping = $departmentPics->get($department->id))
                            <tr class="align-middle transition hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-4 font-semibold">{{ $department->name }}</td>
                                <td class="px-4 py-4 text-slate-500">{{ $department->code ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <form id="department-pic-{{ $department->id }}" method="POST" action="{{ route('internal.master.departments.pic.save', $workspace) }}">
                                        @csrf
                                        <input type="hidden" name="organization_department_id" value="{{ $department->id }}">
                                        <x-searchable-select
                                            :id="'department-pic-select-'.$department->id"
                                            name="pic_public_id"
                                            :selected="$mapping?->pic?->public_id"
                                            placeholder="Choose an IT PIC"
                                            search-placeholder="Search IT members…"
                                            :options="$candidateOptions" />
                                    </form>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <x-button form="department-pic-{{ $department->id }}" variant="secondary">Save</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
