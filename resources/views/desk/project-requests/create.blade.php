@extends('layouts.requester')

@section('title', 'Propose a project')
@section('page-title', 'Propose a project')

@section('content')
    <div class="border-b border-slate-200 pb-6 dark:border-slate-800">
        <h2 class="text-2xl font-bold tracking-tight">Usulkan proyek baru</h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
            Proyek baru itu komitmen yang lebih besar daripada mengubah sesuatu yang sudah ada, jadi perlu
            alasan bisnis, rapat pembahasan dengan supervisor, dan dua tanda tangan.
        </p>
    </div>

    <x-auth-errors class="mt-6" />

    @if ($organizationProfile)
        <x-alert variant="info" :dismissible="false" class="mt-6 max-w-none" title="Alur approval Anda">
            {{ $organizationProfile['department_name'] }} · {{ $organizationProfile['rank_code'] }}.
            {{ $needsDepartmentApproval
                ? 'Usulan masuk ke manager/coordinator department, lalu rapat scoping, approval supervisor ITD, dan approval manager ITD.'
                : 'Usulan langsung masuk ke rapat scoping, approval supervisor ITD, lalu approval manager ITD.' }}
        </x-alert>
    @else
        <x-alert variant="warning" :dismissible="false" class="mt-6 max-w-none" title="Profil organisasi belum terhubung">
            Pastikan email, job rank, dan department Anda tersedia di directory perusahaan sebelum mengirim usulan.
        </x-alert>
    @endif

    @include('desk._how-it-works', [
        'steps' => [
            ['Anda menulis alasan bisnisnya', 'Manfaatnya, konsepnya, bagaimana pekerjaan itu dilakukan sekarang, dan alur yang Anda inginkan. Empat jawaban singkat lebih berguna daripada satu yang panjang.', 'Anda'],
            ['Rapat pembahasan', 'Supervisor mengatur waktu bersama Anda untuk membedah idenya. Tidak ada yang bisa menandatangani sebelum rapat ini terjadi — sistem menolaknya.', 'Anda dan supervisor'],
            ['Tanda tangan pertama', 'Supervisor menyetujui hasil rapat, atau mengembalikannya ke Anda untuk dilengkapi.', 'Supervisor'],
            ['Tanda tangan kedua', 'Manajer ikut menandatangani, dan harus orang yang berbeda. Persetujuan ini yang membuat proyeknya jadi nyata.', 'Manajer'],
            ['Masuk antrean', 'Proyek dijadwalkan berdasarkan kapasitas tim yang sebenarnya, jadi tanggalnya memang bisa ditepati.', 'Otomatis'],
            ['Pekerjaan direncanakan', 'Proyek dipecah jadi tugas beserta perkiraan waktunya, diperiksa oleh orang yang akan mengerjakannya sebelum mulai.', 'Tim IT'],
            ['Anda mengonfirmasi bagian yang hanya Anda yang tahu', 'Apa pun yang mengubah tampilan yang Anda lihat berhenti dulu sampai Anda bilang sudah benar.', 'Anda'],
            ['Selesai', 'Setelah tugas terakhir rampung dan dikonfirmasi, usulan ditutup dan pindah ke tab History.', 'Tim IT'],
        ],
        'writing' => [
            'Mulai dari apa yang didapat perusahaan, bukan apa yang dilakukan aplikasinya. Itu yang ditimbang oleh dua tanda tangan tadi.',
            'Ceritakan proses hari ini apa adanya, termasuk akal-akalan manualnya. Justru itu yang biasanya paling meyakinkan.',
            'Tanggal target itu harapan, bukan komitmen — jadwal sesungguhnya dihitung dari kapasitas setelah disetujui.',
            'Kalau yang diubah sesuatu yang sudah ada, ajukan sebagai Feature saja. Cukup satu persetujuan, bukan dua.',
        ],
    ])

    <form method="POST" action="{{ route('desk.project-requests.store', $workspace) }}" class="mt-6 space-y-6">
        @csrf

        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div>
                <x-label for="title">Mau dinamai apa?</x-label>
                <x-input id="title" name="title" value="{{ old('title') }}" placeholder="Portal mandiri untuk pemasok" required />
                <x-field-error name="title" />
            </div>

            <div>
                <x-label for="benefit">Apa manfaatnya bagi perusahaan?</x-label>
                <x-textarea id="benefit" name="benefit" rows="4" placeholder="Suppliers currently phone us for delivery dates, which takes about 15 hours of the team's week. A portal would remove most of those calls and let us answer the rest faster.">{{ old('benefit') }}</x-textarea>
                <x-field-error name="benefit" />
            </div>

            <div>
                <x-label for="concept">Apa idenya, dengan bahasa sederhana?</x-label>
                <x-textarea id="concept" name="concept" rows="4" placeholder="A website suppliers log into to see their open purchase orders, confirm delivery dates, and upload documents.">{{ old('concept') }}</x-textarea>
                <x-field-error name="concept" />
            </div>

            <div>
                <x-label for="business_process">Proses mana yang didukung atau digantikan?</x-label>
                <x-textarea id="business_process" name="business_process" rows="4" placeholder="Today: supplier calls procurement, procurement checks the ERP, then emails a confirmation. Roughly 40 of these a week.">{{ old('business_process') }}</x-textarea>
                <x-field-error name="business_process" />
            </div>

            <div>
                <x-label for="flow">Bagaimana alurnya dari awal sampai akhir?</x-label>
                <x-textarea id="flow" name="flow" rows="5" placeholder="Supplier signs in → sees open orders → confirms or proposes a new date → procurement is notified → the date is written back to the ERP.">{{ old('flow') }}</x-textarea>
                <x-field-error name="flow" />
            </div>

            <div>
                <x-label for="target_date">Ada tanggal yang Anda harapkan?</x-label>
                <x-input id="target_date" type="date" name="target_date" value="{{ old('target_date') }}" />
                <x-field-error name="target_date" />
                <p class="mt-2 text-xs text-slate-500">Ini harapan, bukan komitmen — tim menjadwalkan berdasarkan kapasitas yang sebenarnya.</p>
            </div>
        </section>

        <div class="flex gap-3">
            <x-button>Kirim usulan</x-button>
            <x-link-button href="{{ route('desk.index') }}" variant="secondary">Batal</x-link-button>
        </div>
    </form>
@endsection
