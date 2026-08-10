@extends('layouts.requester')

@section('title', 'My requests')
@section('page-title', 'My requests')

@php
    $tabs = [
        'feature' => ['Feature', $counts['feature']],
        'project' => ['Project', $counts['project']],
        'history' => ['History', $counts['history']],
    ];
    $link = fn (array $params) => route('desk.index', array_filter($params, fn ($value) => $value !== '' && $value !== null));
@endphp

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4 pb-5">
        <div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ now(auth()->user()->timezone ?: config('app.timezone'))->format('l, F j') }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">Halo {{ auth()->user()->first_name }}</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Permintaan Anda dan fitur dari department yang sama, beserta posisinya sekarang.
            </p>
        </div>
        @if ($workspace)
            <div class="flex flex-wrap gap-2">
                <x-link-button href="{{ route('desk.requests.create', $workspace) }}" variant="secondary"><x-icon name="plus" />Feature</x-link-button>
                <x-link-button href="{{ route('desk.project-requests.create', $workspace) }}"><x-icon name="plus" />Project</x-link-button>
            </div>
        @endif
    </div>

    {{-- Links, not Alpine state: submitting a request redirects back here, and a tab that
         resets itself after every action fights the person using it. --}}
    <x-tabs label="Request types">
        @foreach ($tabs as $key => [$label, $count])
            <a href="{{ $link(['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif
                class="flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold {{ $tab === $key ? 'border-orbit-500 text-orbit-700 dark:text-orbit-300' : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-white' }}">
                {{ $label }}
                @if ($count)
                    <span class="rounded-full bg-slate-100 px-2 text-xs tabular-nums dark:bg-slate-800">{{ $count }}</span>
                @endif
            </a>
        @endforeach
    </x-tabs>

    @if ($statuses->count() > 1)
        <div class="mt-5 flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</span>
            <a href="{{ $link(['tab' => $tab]) }}" class="rounded-full border px-3 py-1 text-xs font-semibold {{ $status === '' ? 'border-orbit-500 bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : 'border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800' }}">Semua</a>
            @foreach ($statuses as $option)
                <a href="{{ $link(['tab' => $tab, 'status' => $option->value]) }}" class="rounded-full border px-3 py-1 text-xs font-semibold {{ $status === $option->value ? 'border-orbit-500 bg-orbit-50 text-orbit-800 dark:bg-orbit-950/60 dark:text-orbit-200' : 'border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800' }}">{{ $option->label() }}</a>
            @endforeach
        </div>
    @endif

    @if ($rows->isEmpty())
        <div class="mt-6">
            <x-empty-state
                icon="list"
                :title="$status !== '' ? 'Tidak ada yang berstatus itu' : match ($tab) {
                    'project' => 'Belum ada usulan proyek',
                    'history' => 'Belum ada yang selesai',
                    default => 'Belum ada permintaan fitur',
                }"
                :description="$status !== ''
                    ? 'Hapus filternya untuk melihat sisanya.'
                    : match ($tab) {
                        'project' => 'Ajukan sesuatu yang baru, dan usulannya muncul di sini beserta tahapan dan kedua tanda tangannya.',
                        'history' => 'Permintaan yang sudah selesai, ditolak, atau dibatalkan tersimpan di sini.',
                        default => 'Ajukan perubahan pada sistem yang Anda pakai, dan permintaannya muncul di sini beserta tahapannya.',
                    }" />
        </div>
    @else
        <x-table class="mt-6 bg-white dark:bg-slate-900">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400 dark:border-slate-800">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold">Permintaan</th>
                    <th scope="col" class="px-4 py-3 font-semibold">{{ $tab === 'project' ? 'Jenis' : 'Sistem' }}</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                    <th scope="col" class="px-4 py-3 font-semibold whitespace-nowrap">Diajukan</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Buka</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php($isProject = $row instanceof App\Models\ProjectRequest)
                    @php($needsYou = (! $isProject && $row->requester_id === auth()->id() && $row->status === App\Enums\FeatureRequestStatus::NEEDS_INFO))
                    <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/60">
                        <td class="max-w-xs px-4 py-3">
                            <a href="{{ $isProject ? route('desk.project-requests.show', $row) : route('desk.requests.show', $row) }}"
                                class="block font-semibold hover:underline">{{ $row->title }}</a>
                            @if (! $isProject && $row->requester_id !== auth()->id())
                                <p class="mt-1 text-xs text-slate-500">Diajukan oleh {{ $row->requester->name }} · {{ $row->requester_department_code }}</p>
                            @endif
                            {{-- Only the line that asks something of the reader survives into the
                                 table; the rest of the description is one click away. --}}
                            @if ($needsYou)
                                <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Mereka butuh penjelasan tambahan dari Anda.</p>
                            @elseif ($isProject && $row->status === App\Enums\ProjectRequestStatus::PENDING_MEETING)
                                <p class="mt-1 text-xs text-slate-500">Menunggu supervisor mengatur rapat pembahasan.</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $isProject ? 'Proyek' : $row->system->name }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <x-badge :tone="$row->status->tone()">{{ $row->status->label() }}</x-badge>
                                @if (! $isProject && $row->urgency->value === 'high')
                                    <x-badge tone="danger">Mendesak</x-badge>
                                @endif
                                @if (! $isProject && $row->requester_id !== auth()->id())
                                    <x-badge tone="slate">Department</x-badge>
                                @endif
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $row->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ $isProject ? route('desk.project-requests.show', $row) : route('desk.requests.show', $row) }}"
                                class="inline-grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                                aria-label="Buka {{ $row->title }}"><x-icon name="chevron-right" class="size-4" /></a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    @endif
@endsection
