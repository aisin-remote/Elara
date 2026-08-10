{{-- Shared by both request forms. Steps are passed in because the two paths genuinely differ
     (one approval versus a meeting and two), but everything after approval is identical and
     is described here once. Content is Indonesian; the chrome around it is not. --}}
@props(['steps' => [], 'writing' => []])

@php($window = app(App\Services\WorkspaceSettings::class)->validationWindowDays($workspace))

<div x-data="{ open: false }" class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <button type="button" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'"
        class="flex w-full items-center gap-3 p-5 text-left hover:bg-slate-50 dark:hover:bg-slate-800/60">
        <x-icon name="info" class="size-5 shrink-0 text-orbit-500" />
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold">Cara kerjanya, dari sini sampai selesai</span>
            <span class="mt-0.5 block text-xs text-slate-500">{{ count($steps) }} tahap, dan apa yang diharapkan dari Anda di tiap tahap.</span>
        </span>
        <x-icon name="chevron-right" class="size-4 shrink-0 text-slate-400 transition" ::class="open ? 'rotate-90' : ''" />
    </button>

    <div x-show="open" x-cloak class="border-t border-slate-100 p-5 dark:border-slate-800">
        {{-- A stepper, not a list: the connecting line is what says "these happen in order",
             and the filled markers say which stops are yours. --}}
        <ol class="relative">
            @foreach ($steps as $index => [$title, $body, $who])
                @php($mine = $who === 'Anda')
                <li class="relative grid grid-cols-[2.25rem_1fr] gap-4 pb-7 last:pb-0">
                    @unless ($loop->last)
                        <span class="absolute left-[1.125rem] top-9 h-[calc(100%-2.25rem)] w-px -translate-x-1/2 bg-slate-200 dark:bg-slate-700" aria-hidden="true"></span>
                    @endunless

                    <span class="z-10 grid size-9 place-items-center rounded-full border-2 text-sm font-bold {{ $mine
                        ? 'border-orbit-500 bg-orbit-500 text-white'
                        : 'border-slate-200 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-900' }}">{{ $index + 1 }}</span>

                    <div class="min-w-0 pt-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold">{{ $title }}</p>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $mine
                                ? 'bg-orbit-50 text-orbit-700 dark:bg-orbit-950/60 dark:text-orbit-200'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">{{ $who }}</span>
                        </div>
                        <p class="mt-1 text-sm leading-6 text-slate-500">{{ $body }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        {{-- The one consequence a requester carries alone, stated with its real number rather
             than "soon", because this is where silence costs them the whole request. --}}
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Satu tenggat yang jadi tanggung jawab Anda</p>
            <p class="mt-1 text-sm leading-6 text-amber-800 dark:text-amber-200/90">
                Ketika tim menyelesaikan sesuatu yang hanya Anda yang bisa menilainya, itu muncul di menu
                <span class="font-semibold">Waiting on me</span>. Anda punya
                <span class="font-semibold">{{ $window }} hari</span> untuk menjawab. Tanpa jawaban,
                permintaan Anda dibatalkan dan jatahnya di antrean diberikan ke orang lain — jadi menjawab
                “ini belum benar” selalu lebih baik daripada tidak menjawab sama sekali.
            </p>
        </div>

        @if ($writing !== [])
            <div class="mt-6">
                <p class="text-sm font-semibold">Cara menulis permintaan yang cepat disetujui</p>
                <ul class="mt-2 space-y-2">
                    @foreach ($writing as $tip)
                        <li class="flex gap-2 text-sm leading-6 text-slate-500">
                            <x-icon name="check" class="mt-1 size-4 shrink-0 text-emerald-500" />
                            <span>{{ $tip }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
