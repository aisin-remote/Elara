@extends('layouts.requester')

@section('title', 'Validations')
@section('page-title', 'Waiting on me')

@section('content')
    <div class="pb-6">
        <h2 class="text-2xl font-bold tracking-tight">{{ $open->count() }} menunggu jawaban Anda</h2>
        <p class="mt-2 text-sm text-slate-500">
            Tim berhenti di sini sampai Anda mengonfirmasi. Kalau tidak dijawab tepat waktu, permintaannya dibatalkan
            dan jatahnya di antrean diberikan ke orang lain.
        </p>
    </div>

    @forelse ($open as $checkpoint)
        @php($urgent = $checkpoint->daysLeft() <= 1)
        <section class="mb-4 rounded-2xl border bg-white p-5 dark:bg-slate-900 {{ $urgent ? 'border-rose-300 dark:border-rose-900' : 'border-slate-200 dark:border-slate-800' }}"
            aria-labelledby="checkpoint-{{ $checkpoint->public_id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $checkpoint->subject?->title }}</p>
                    <h2 id="checkpoint-{{ $checkpoint->public_id }}" class="mt-1 text-lg font-bold">{{ $checkpoint->task->title }}</h2>
                </div>
                <x-badge :tone="$urgent ? 'danger' : 'warning'">{{ $checkpoint->countdown() }}</x-badge>
            </div>

            @if ($checkpoint->reason)
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $checkpoint->reason }}</p>
            @endif

            @if ($checkpoint->task->description)
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-500">{{ $checkpoint->task->description }}</p>
            @endif

            {{-- The consequence spelled out with a date. A deadline nobody knows about is not
                 a deadline, and "expires soon" is not a date. --}}
            <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500 dark:bg-slate-800/60">
                Kalau tidak ada jawaban sampai <strong>{{ $checkpoint->expires_at->format('j F Y') }}</strong>,
                “{{ $checkpoint->subject?->title }}” dibatalkan dan tim melanjutkan ke permintaan berikutnya.
            </p>

            <form method="POST" action="{{ route('desk.validations.respond', $checkpoint) }}" class="mt-4 space-y-3"
                x-data="{ decision: 'approved' }">
                @csrf
                <x-form-errors :except="['decision', 'response_note']" />

                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ([['approved', 'Sudah benar', 'Tim melanjutkan pekerjaan.'], ['changes_requested', 'Perlu diperbaiki', 'Dikembalikan ke tim beserta catatan Anda.']] as [$value, $label, $hint])
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                            <input type="radio" name="decision" value="{{ $value }}" x-model="decision" @checked($value === 'approved') class="mt-1 border-slate-300 text-orbit-600 focus:ring-orbit-500">
                            <span class="min-w-0"><span class="block text-sm font-semibold">{{ $label }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $hint }}</span></span>
                        </label>
                    @endforeach
                </div>

                <div x-show="decision === 'changes_requested'" x-cloak>
                    <x-label :for="'note_'.$checkpoint->public_id">Apa yang perlu diperbaiki?</x-label>
                    <x-textarea :id="'note_'.$checkpoint->public_id" name="response_note" rows="3" placeholder="Jelaskan apa yang salah atau belum ada.">{{ old('response_note') }}</x-textarea>
                    <x-field-error name="response_note" />
                </div>

                <x-button>Kirim jawaban saya</x-button>
            </form>
        </section>
    @empty
        <x-empty-state icon="check" title="Tidak ada yang menunggu Anda"
            description="Kalau tim menyelesaikan sesuatu yang perlu Anda konfirmasi, itu akan muncul di sini." />
    @endforelse

    @if ($answered->isNotEmpty())
        <section class="mt-10" aria-labelledby="answered-title">
            <h2 id="answered-title" class="text-lg font-bold">Sudah dijawab</h2>
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                @foreach ($answered as $checkpoint)
                    <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4 last:border-0 dark:border-slate-800">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $checkpoint->task->title }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">
                                {{ $checkpoint->subject?->title }}
                                @if ($checkpoint->responded_at)
                                    · dijawab {{ $checkpoint->responded_at->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                        <x-badge :tone="$checkpoint->status->tone()">{{ $checkpoint->status->label() }}</x-badge>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
