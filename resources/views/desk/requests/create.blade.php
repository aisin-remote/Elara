@extends('layouts.requester')

@section('title', 'New request')
@section('page-title', 'New request')

@section('content')
    <div class="border-b border-slate-200 pb-6 dark:border-slate-800">
        <h2 class="text-2xl font-bold tracking-tight">Ajukan perubahan</h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
            Ceritakan masalahnya dan seperti apa hasil yang baik menurut Anda. Supervisor akan meninjau,
            lalu tim yang mengubahnya jadi pekerjaan — Anda tidak perlu menjelaskan cara membuatnya.
        </p>
    </div>

    <x-auth-errors class="mt-6" />

    @if ($organizationProfile)
        <x-alert variant="info" :dismissible="false" class="mt-6 max-w-none" title="Alur approval Anda">
            {{ $organizationProfile['department_name'] }} · {{ $organizationProfile['rank_code'] }}.
            {{ $needsDepartmentApproval
                ? 'Permintaan masuk ke manager/coordinator department lebih dulu, lalu ke supervisor ITD.'
                : 'Permintaan langsung masuk ke supervisor ITD.' }}
        </x-alert>
    @else
        <x-alert variant="warning" :dismissible="false" class="mt-6 max-w-none" title="Profil organisasi belum terhubung">
            Pastikan email, job rank, dan department Anda tersedia di directory perusahaan sebelum mengirim permintaan.
        </x-alert>
    @endif

    @include('desk._how-it-works', [
        'steps' => [
            ['Anda menjelaskan masalahnya', 'Ceritakan apa yang sulit hari ini dan seperti apa hasil yang Anda harapkan. Bukan cara membuatnya — itu bagian tim.', 'Anda'],
            ['Supervisor meninjau', 'Mereka menyetujui, menolak dengan alasan, atau meminta penjelasan tambahan. Kalau diminta, permintaan kembali ke Anda dan tetap terbuka.', 'Supervisor'],
            ['Masuk antrean', 'Permintaan yang disetujui dijadwalkan berdasarkan kapasitas tim yang sebenarnya, jadi tanggalnya memang bisa ditepati, bukan sekadar janji.', 'Otomatis'],
            ['Pekerjaan direncanakan', 'Permintaan dipecah jadi tugas beserta perkiraan waktunya, dan orang yang akan mengerjakannya memeriksa rencana itu sebelum mulai.', 'Tim IT'],
            ['Anda mengonfirmasi bagian yang hanya Anda yang tahu', 'Apa pun yang mengubah tampilan yang Anda lihat — layar, format laporan, angka — berhenti dulu sampai Anda bilang sudah benar.', 'Anda'],
            ['Selesai', 'Setelah tugas terakhir rampung dan dikonfirmasi, permintaan ditutup dan pindah ke tab History.', 'Tim IT'],
        ],
        'writing' => [
            'Jelaskan masalahnya, bukan solusi yang sudah Anda bayangkan. Tim sering tahu jalan yang lebih murah ke hasil yang sama.',
            'Sebutkan seberapa sering terjadi dan berapa lama waktu Anda tersita. Itu yang menentukan posisinya di antrean.',
            'Sebutkan sistem yang benar-benar Anda pakai, walau Anda belum yakin itu yang tepat.',
            'Tandai mendesak hanya kalau menunggu benar-benar merugikan. Kalau semua mendesak, tidak ada yang mendesak.',
        ],
    ])

    @if ($systems->isEmpty())
        <x-alert variant="info" :dismissible="false" class="mt-6 max-w-none">
            Belum ada sistem yang dibuka untuk permintaan. Minta administrator menambahkan sistem yang Anda
            pakai di Settings → Master data.
        </x-alert>
    @else
        <form method="POST" action="{{ route('desk.requests.store', $workspace) }}" class="mt-6 space-y-6">
            @csrf

            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <x-label for="system_public_id">Sistem yang mana?</x-label>
                <x-select id="system_public_id" name="system_public_id" required>
                    <option value="">Pilih sistem</option>
                    @foreach ($systems as $system)
                        <option value="{{ $system->public_id }}" @selected(old('system_public_id') === $system->public_id)>
                            {{ $system->name }}{{ ($queueDepth[$system->public_id] ?? 0) > 0 ? ' — '.$queueDepth[$system->public_id].' antre di depan Anda' : '' }}
                        </option>
                    @endforeach
                </x-select>
                <x-field-error name="system_public_id" />
                <p class="mt-2 text-xs text-slate-500">Tiap sistem punya penanggung jawab yang paling paham; permintaan Anda masuk ke dia lebih dulu.</p>
            </section>

            <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <x-label for="title">Beri judul singkat</x-label>
                    <x-input id="title" name="title" value="{{ old('title') }}" placeholder="Ekspor laporan stok bulanan" required />
                    <x-field-error name="title" />
                </div>

                <div>
                    <x-label for="problem">Apa masalahnya sekarang?</x-label>
                    <x-textarea id="problem" name="problem" rows="4" placeholder="Sekarang kami menyalin angkanya ke spreadsheet secara manual tiap bulan, makan waktu dua hari dan sering salah.">{{ old('problem') }}</x-textarea>
                    <x-field-error name="problem" />
                </div>

                <div>
                    <x-label for="desired_outcome">Seperti apa hasil yang Anda harapkan?</x-label>
                    <x-textarea id="desired_outcome" name="desired_outcome" rows="4" placeholder="Saya bisa mengunduh laporan yang sama dari sistem, dengan kolom yang sekarang kami pakai.">{{ old('desired_outcome') }}</x-textarea>
                    <x-field-error name="desired_outcome" />
                </div>

                <div>
                    <x-label for="urgency">Seberapa mendesak?</x-label>
                    <x-select id="urgency" name="urgency">
                        @foreach ($urgencies as $urgency)
                            <option value="{{ $urgency->value }}" @selected(old('urgency', 'normal') === $urgency->value)>{{ $urgency->label() }}</option>
                        @endforeach
                    </x-select>
                    <p class="mt-2 text-xs text-slate-500">Tingkat urgensi membantu peninjau mengurutkan antrean. Ini tidak membuat Anda naik di jadwal.</p>
                </div>
            </section>

            <div class="flex gap-3">
                <x-button>Kirim permintaan</x-button>
                <x-link-button href="{{ route('desk.index') }}" variant="secondary">Batal</x-link-button>
            </div>
        </form>
    @endif
@endsection
