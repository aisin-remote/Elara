<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>ELARA — Panduan Pengguna (Requester)</title>
    @include('docs._style')
</head>
<body>

{{-- COVER --}}
<div class="cover">
    <div class="cover-logo">E</div>
    <div class="cover-badge">PANDUAN PENGGUNA &mdash; EDISI REQUESTER</div>
    <h1>ELARA</h1>
    <p class="cover-sub">Cara mengajukan dan memantau permintaan ke tim ITD</p>
    <p class="cover-sub" style="margin-top: 14pt;"><strong>Perusahaan:</strong> AIIA</p>
    <p class="cover-sub"><strong>Untuk:</strong> Seluruh department selain ITD</p>
    <p class="cover-sub"><strong>Alamat aplikasi:</strong> https://elara-qa.aiia.co.id</p>
    <p class="cover-sub"><strong>Versi dokumentasi:</strong> {{ $version }}</p>
    <p class="cover-sub"><strong>Tanggal:</strong> {{ $generatedAt }}</p>
    <p class="cover-meta">Panduan singkat untuk pengguna awam.<br>Tidak memuat penjelasan teknis.</p>
</div>

{{-- DAFTAR ISI --}}
<div class="toc">
    <h2>Daftar Isi</h2>
    <table>
        @foreach ([
            ['1', 'Elara Itu Apa?'],
            ['2', 'Siapa Saja yang Terlibat'],
            ['3', 'Gambaran Alur dari Awal sampai Selesai'],
            ['4', 'Tiga Jenis Permintaan'],
            ['5', 'Menu yang Anda Gunakan'],
            ['6', 'Langkah Membuat Permintaan'],
            ['7', 'Alur Approval'],
            ['8', 'Arti Setiap Status'],
            ['9', 'Waiting on me — Saat ITD Menunggu Anda'],
            ['10', 'Memantau Permintaan'],
            ['11', 'Jika Ditolak atau Diminta Melengkapi'],
            ['12', 'Notifikasi'],
            ['13', 'Tombol Penting'],
            ['14', 'Pertanyaan yang Sering Ditanyakan'],
        ] as [$num, $title])
            <tr><td class="toc-num">{{ $num }}.</td><td>{{ $title }}</td></tr>
        @endforeach
    </table>
</div>

{{-- 1 --}}
<h2><span class="section-num">1.</span> Elara Itu Apa?</h2>

<p><strong>Elara</strong> adalah aplikasi tempat seluruh department mengajukan pekerjaan ke tim <strong>ITD</strong>
   dan memantau perkembangannya. Semua permintaan tercatat di satu tempat: siapa yang mengajukan, siapa yang menyetujui,
   siapa yang mengerjakan, dan kapan selesai.</p>

<h3>Yang bisa Anda lakukan</h3>
<ul>
    <li>Mengajukan perubahan fitur pada aplikasi yang sudah Anda pakai sehari-hari.</li>
    <li>Mengusulkan proyek atau aplikasi baru.</li>
    <li>Meminta bantuan operasional (perangkat, jaringan, akun aplikasi, dan sejenisnya).</li>
    <li>Melihat posisi permintaan Anda tanpa perlu bertanya lewat chat atau telepon.</li>
    <li>Menjawab pertanyaan ITD dan mengirim berkas pendukung.</li>
</ul>

<div class="note"><strong>Catatan:</strong> Anda tidak perlu mengetahui istilah teknis apa pun. Cukup jelaskan
    kondisi sekarang, kondisi yang diinginkan, dan manfaatnya bagi pekerjaan Anda.</div>

<h3>Cara masuk</h3>
<div class="flow">Buka https://elara-qa.aiia.co.id
    ↓
Masukkan email &amp; password akun perusahaan
(akun yang sama dengan aplikasi AIIA lainnya)
    ↓
Halaman Request Desk terbuka</div>

{{-- 2 --}}
<h2 class="page-break"><span class="section-num">2.</span> Siapa Saja yang Terlibat</h2>

<table class="data">
    <tr><th>Peran</th><th>Siapa</th><th>Tugasnya dalam Elara</th></tr>
    <tr>
        <td><strong>Requester</strong></td>
        <td>Seluruh karyawan department non-ITD</td>
        <td>Membuat permintaan, melengkapi data, menjawab pertanyaan ITD, memantau status</td>
    </tr>
    <tr>
        <td><strong>Manager / Coordinator Department</strong></td>
        <td>Atasan di department Anda sendiri (jabatan MGR atau COOR)</td>
        <td>Menyetujui permintaan anggota department-nya sebelum diteruskan ke ITD</td>
    </tr>
    <tr>
        <td><strong>Supervisor / Manager ITD</strong></td>
        <td>Tim ITD</td>
        <td>Menilai permintaan, menyetujui atau menolak, menunjuk PIC, dan menjadwalkan</td>
    </tr>
    <tr>
        <td><strong>PIC ITD</strong></td>
        <td>Anggota ITD yang ditunjuk</td>
        <td>Mengerjakan permintaan sampai selesai dan meminta konfirmasi Anda</td>
    </tr>
</table>

<div class="note"><strong>Penting:</strong> Jabatan Anda di direktori perusahaan menentukan peran otomatis di Elara.
    Jika jabatan Anda <strong>MGR</strong> atau <strong>COOR</strong>, Anda mendapat menu <strong>Approvals</strong>
    tambahan untuk menyetujui permintaan anggota department Anda. Jabatan lain (STF, LDR, OP, dan sejenisnya)
    berperan sebagai requester.</div>

<h3>Batasan yang perlu diketahui</h3>
<ul>
    <li>Anda hanya melihat permintaan Anda sendiri dan permintaan department Anda.</li>
    <li>Anda tidak dapat menyetujui permintaan department lain.</li>
    <li>Anda tidak dapat mengubah jadwal atau data internal tim ITD.</li>
    <li>Manager/Coordinator hanya dapat menyetujui permintaan dari department-nya sendiri.</li>
</ul>

{{-- 3 --}}
<h2 class="page-break"><span class="section-num">3.</span> Gambaran Alur dari Awal sampai Selesai</h2>

<p>Ini perjalanan sebuah permintaan, mulai dari Anda menekan tombol Submit sampai pekerjaan dinyatakan selesai.</p>

<div class="flow">[ANDA] Membuat permintaan → Submit
    ↓
[MANAGER / COORDINATOR DEPARTMENT ANDA]
Membaca dan memutuskan
    ├── Approve → lanjut ke ITD
    ├── Reject → permintaan ditutup, alasan tertulis
    └── Minta info → kembali ke Anda untuk dilengkapi
    ↓
[SUPERVISOR / MANAGER ITD]
Menilai permintaan
    ├── Approve → ditentukan PIC + perkiraan jam kerja
    ├── Reject → permintaan ditutup, alasan tertulis
    └── Ask for more detail → kembali ke Anda
    ↓
[SISTEM] Menjadwalkan pekerjaan
sesuai kapasitas tim ITD yang tersedia
    ↓
[PIC ITD] Mengerjakan
(bila ada yang kurang jelas, ITD bertanya ke Anda)
    ↓
[ANDA] Konfirmasi hasil pekerjaan
    ↓
SELESAI (Delivered)</div>

<div class="note"><strong>Jika jabatan Anda MGR atau COOR:</strong> permintaan Anda tidak melewati approval department,
    langsung masuk ke antrean ITD.</div>

{{-- 4 --}}
<h2 class="page-break"><span class="section-num">4.</span> Tiga Jenis Permintaan</h2>

<p>Pilih jenis yang sesuai agar permintaan tidak salah jalur.</p>

<table class="data">
    <tr><th>Jenis</th><th>Gunakan bila</th><th>Contoh</th><th>Approval yang dilalui</th></tr>
    <tr>
        <td><strong>Feature</strong></td>
        <td>Menambah atau mengubah fitur pada sistem yang <strong>sudah ada</strong></td>
        <td>Menambah tombol export di QIRA; menambah filter tanggal di PREMAN</td>
        <td>Manager department (bila perlu) &rarr; <strong>satu</strong> approval ITD</td>
    </tr>
    <tr>
        <td><strong>Project</strong></td>
        <td>Mengusulkan sistem atau aplikasi <strong>baru</strong>, atau pekerjaan besar</td>
        <td>Membuat aplikasi monitoring baru untuk satu line produksi</td>
        <td>Manager department &rarr; meeting pembahasan &rarr; <strong>dua</strong> tanda tangan ITD (Supervisor, lalu Manager)</td>
    </tr>
    <tr>
        <td><strong>Supporting</strong></td>
        <td>Bantuan operasional di luar pengembangan sistem</td>
        <td>Perangkat/hardware, jaringan, instalasi software, masalah akun</td>
        <td>Langsung masuk antrean bantuan ITD</td>
    </tr>
</table>

<div class="note"><strong>Ragu memilih?</strong> Kalau aplikasinya sudah Anda pakai hari ini dan hanya perlu diubah,
    itu <strong>Feature</strong>. Kalau belum ada aplikasinya sama sekali, itu <strong>Project</strong>.</div>

{{-- 5 --}}
<h2 class="page-break"><span class="section-num">5.</span> Menu yang Anda Gunakan</h2>

<div class="flow">Timeline
My requests
Waiting on me
Approvals            ← hanya untuk Manager/Coordinator department

Raise something new:
  ├── Feature
  ├── Project
  └── Supporting</div>

<table class="data">
    <tr><th>Menu</th><th>Isinya</th><th>Kapan dibuka</th></tr>
    <tr><td><strong>Timeline</strong></td><td>Jadwal pekerjaan ITD yang sedang berjalan</td><td>Ingin tahu kesibukan ITD dan perkiraan giliran</td></tr>
    <tr><td><strong>My requests</strong></td><td>Seluruh permintaan Anda: Feature, Project, Supporting, dan History</td><td>Memantau status dan membuka detail</td></tr>
    <tr><td><strong>Waiting on me</strong></td><td>Hal yang ITD tunggu dari Anda</td><td>Ada angka merah pada menu &mdash; wajib ditindaklanjuti</td></tr>
    <tr><td><strong>Approvals</strong></td><td>Antrean permintaan anggota department Anda</td><td>Khusus Manager/Coordinator</td></tr>
    <tr><td><strong>Raise something new</strong></td><td>Formulir permintaan baru</td><td>Setiap kali mengajukan pekerjaan ke ITD</td></tr>
</table>

{{-- 6 --}}
<h2 class="page-break"><span class="section-num">6.</span> Langkah Membuat Permintaan</h2>

<h3>A. Feature Request</h3>
<ol>
    <li>Sidebar kiri &rarr; <strong>Feature</strong>.</li>
    <li>Pilih <strong>sistem</strong> yang ingin diubah dari daftar.</li>
    <li>Isi <strong>Judul</strong> &mdash; singkat dan jelas, misalnya &ldquo;Tambah filter tanggal pada laporan harian&rdquo;.</li>
    <li>Isi <strong>Kondisi saat ini</strong> &mdash; apa yang terjadi sekarang dan apa kesulitannya.</li>
    <li>Isi <strong>Kondisi yang diinginkan</strong> &mdash; hasil seperti apa yang Anda harapkan.</li>
    <li>Isi <strong>Manfaat</strong> &mdash; apa untungnya bagi pekerjaan atau department.</li>
    <li>Pilih <strong>Urgensi</strong>: Low, Normal, atau High.</li>
    <li>Tekan <strong>Submit</strong>.</li>
</ol>

<div class="note"><strong>Tiga kolom penjelasan wajib diisi minimal 20 karakter.</strong> Penjelasan yang terlalu pendek
    membuat permintaan dikembalikan untuk dilengkapi, sehingga prosesnya justru lebih lama.</div>

<h3>B. Project Request</h3>
<ol>
    <li>Sidebar kiri &rarr; <strong>Project</strong>.</li>
    <li>Isi Judul, Latar belakang, dan Mengapa dibutuhkan.</li>
    <li>Isi Tujuan (1&ndash;6 poin, masing-masing dengan penjelasan).</li>
    <li>Isi Gambaran solusi, Kondisi sebelum, dan Kondisi sesudah.</li>
    <li>Isi Manfaat (1&ndash;4 poin) dan Perkiraan biaya (1&ndash;3 poin).</li>
    <li>Isi Perkiraan keuntungan (ROI), serta Target tanggal bila ada.</li>
    <li>Tekan <strong>Submit</strong>.</li>
</ol>

<h3>C. Supporting Request</h3>
<ol>
    <li>Sidebar kiri &rarr; <strong>Supporting</strong>.</li>
    <li>Isi Judul dan penjelasan kebutuhan.</li>
    <li>Pilih Kategori: Hardware, Software, Network, atau Other.</li>
    <li>Pilih Prioritas, dan tanggal dibutuhkan bila ada.</li>
    <li>Tekan <strong>Send</strong>.</li>
</ol>

{{-- 7 --}}
<h2 class="page-break"><span class="section-num">7.</span> Alur Approval</h2>

<h3>Feature Request</h3>
<div class="flow">ANDA → Submit
    ↓
Manager/Coordinator department
(dilewati bila Anda sendiri MGR/COOR)
    ├── Approve → Under review di ITD
    ├── Reject → Rejected (selesai, ada alasan)
    └── Minta info → Needs your input (kembali ke Anda)
    ↓
Supervisor ATAU Manager ITD — cukup satu tanda tangan
    ├── Approve → ditunjuk PIC + perkiraan jam kerja
    ├── Reject → Rejected
    └── Ask for more detail → Needs your input
    ↓
Scheduled → In progress → Delivered</div>

<h3>Project Request</h3>
<div class="flow">ANDA → Submit
    ↓
Manager/Coordinator department
    ↓
Meeting pembahasan bersama ITD (scoping)
    ↓
Tanda tangan 1: Supervisor ITD
    ↓
Tanda tangan 2: Manager ITD
    ↓
Approved → Scheduled → In progress → Delivered</div>

<div class="note"><strong>Bedanya:</strong> Feature cukup satu tanda tangan ITD &mdash; Supervisor atau Manager, siapa pun
    yang lebih dulu tersedia. Project wajib berurutan: Supervisor dulu, baru Manager.</div>

<h3>Apa yang terjadi setelah keputusan</h3>
<table class="data">
    <tr><th>Keputusan</th><th>Yang terjadi</th><th>Tindakan Anda</th></tr>
    <tr><td><strong>Approve</strong></td><td>Permintaan lanjut ke tahap berikutnya; Anda menerima notifikasi</td><td>Menunggu &mdash; pantau lewat My requests</td></tr>
    <tr><td><strong>Reject</strong></td><td>Permintaan ditutup beserta alasannya</td><td>Baca alasan; bila masih dibutuhkan, ajukan permintaan baru yang sudah diperbaiki</td></tr>
    <tr><td><strong>Minta info / Ask for more detail</strong></td><td>Permintaan kembali ke Anda dan tetap terbuka</td><td>Lengkapi jawaban lalu kirim ulang</td></tr>
</table>

{{-- 8 --}}
<h2 class="page-break"><span class="section-num">8.</span> Arti Setiap Status</h2>

<table class="data">
    <tr><th>Status</th><th>Artinya</th><th>Yang harus Anda lakukan</th></tr>
    <tr><td><strong>Draft</strong></td><td>Belum terkirim</td><td>Lengkapi lalu Submit</td></tr>
    <tr><td><strong>Menunggu approval department</strong></td><td>Ada di meja Manager/Coordinator department Anda</td><td>Menunggu; boleh diingatkan langsung</td></tr>
    <tr><td><strong>Under review</strong></td><td>Sedang dinilai Supervisor/Manager ITD</td><td>Menunggu</td></tr>
    <tr><td><strong>Needs your input</strong></td><td>Ada yang perlu Anda jelaskan</td><td><strong>Segera jawab</strong> &mdash; permintaan berhenti sampai Anda menjawab</td></tr>
    <tr><td><strong>Approved</strong></td><td>Disetujui, menunggu jadwal</td><td>Menunggu penjadwalan</td></tr>
    <tr><td><strong>Scheduled</strong></td><td>Sudah punya tanggal mulai dan target selesai</td><td>Menunggu pengerjaan</td></tr>
    <tr><td><strong>In progress</strong></td><td>Sedang dikerjakan PIC ITD</td><td>Siap menjawab bila ITD bertanya</td></tr>
    <tr><td><strong>Delivered</strong></td><td>Pekerjaan selesai</td><td>Tidak ada &mdash; gunakan hasilnya</td></tr>
    <tr><td><strong>Rejected</strong></td><td>Ditolak, atau Anda tarik sendiri</td><td>Baca alasannya</td></tr>
    <tr><td><strong>Taken down</strong></td><td>Dibatalkan karena konfirmasi Anda tidak masuk sampai batas waktu</td><td>Ajukan ulang bila masih dibutuhkan</td></tr>
</table>

<div class="flow">Draft → Menunggu approval department → Under review → Approved
   → Scheduled → In progress → Delivered

Jalur lain:
   Needs your input  → dijawab → kembali ke antrean semula
   Rejected          → berhenti (permintaan ditutup)
   Taken down        → berhenti (konfirmasi lewat batas waktu)</div>

{{-- 9 --}}
<h2 class="page-break"><span class="section-num">9.</span> Waiting on me &mdash; Saat ITD Menunggu Anda</h2>

<p>Menu ini berisi semua hal yang membuat pekerjaan ITD berhenti karena menunggu Anda. Ada <strong>dua jenis</strong>:</p>

<table class="data">
    <tr><th>Jenis</th><th>Muncul kapan</th><th>Yang Anda lakukan</th><th>Bila didiamkan</th></tr>
    <tr>
        <td><strong>Konfirmasi hasil</strong><br><span style="font-size:8pt;color:#64748b;">ada hitung mundur</span></td>
        <td>ITD selesai mengerjakan sesuatu yang hanya bisa dinilai benar atau salah oleh Anda</td>
        <td>Pilih <em>Looks correct</em> bila sudah sesuai, atau <em>Changes required</em> disertai catatan bila belum</td>
        <td><strong>Lewat batas waktu &rarr; permintaan dibatalkan otomatis</strong> dan jadwal ITD dialihkan ke antrean lain</td>
    </tr>
    <tr>
        <td><strong>Pertanyaan dari ITD</strong><br><span style="font-size:8pt;color:#64748b;">tanpa hitung mundur</span></td>
        <td>ITD membutuhkan data atau keterangan untuk melanjutkan, misalnya daftar part number</td>
        <td>Tulis jawaban pada kolom <em>Your answer</em></td>
        <td>Tidak dibatalkan, tetapi pekerjaan Anda berhenti selama belum dijawab</td>
    </tr>
</table>

<h3>Mengirim berkas</h3>
<p>Pada kedua jenis di atas tersedia kolom <strong>Attach files</strong>: maksimal 5 berkas per jawaban
   (Excel, Word, PDF, gambar, atau zip). Berkas langsung menempel pada pekerjaan ITD yang bersangkutan,
   sehingga tidak perlu dikirim lewat email atau chat.</p>

<div class="note"><strong>Prioritaskan menu ini.</strong> Angka merah pada menu <em>Waiting on me</em> berarti
    ada pekerjaan yang berhenti dan menunggu Anda.</div>

{{-- 10 --}}
<h2 class="page-break"><span class="section-num">10.</span> Memantau Permintaan</h2>

<ol>
    <li>Buka <strong>My requests</strong>.</li>
    <li>Pilih tab: <strong>Feature</strong>, <strong>Project</strong>, <strong>Supporting</strong>, atau <strong>History</strong> (yang sudah selesai atau ditutup).</li>
    <li>Klik salah satu baris untuk membuka detail.</li>
</ol>

<p>Pada halaman detail Anda dapat melihat:</p>
<table class="data">
    <tr><th>Informasi</th><th>Menjawab pertanyaan</th></tr>
    <tr><td>Status saat ini</td><td>Sekarang permintaan saya ada di tahap mana?</td></tr>
    <tr><td>Riwayat aktivitas (timeline)</td><td>Siapa melakukan apa, dan kapan?</td></tr>
    <tr><td>Catatan approval</td><td>Kenapa disetujui, ditolak, atau dikembalikan?</td></tr>
    <tr><td>PIC ITD</td><td>Siapa yang mengerjakan permintaan saya?</td></tr>
    <tr><td>Tanggal mulai &amp; target selesai</td><td>Kapan dikerjakan dan kapan diperkirakan selesai?</td></tr>
    <tr><td>Progres pekerjaan</td><td>Sudah sampai mana?</td></tr>
    <tr><td>Lampiran</td><td>Berkas apa saja yang sudah dipertukarkan?</td></tr>
</table>

<p>Menu <strong>Timeline</strong> menampilkan gambaran keseluruhan jadwal ITD, berguna untuk memperkirakan
   giliran pekerjaan Anda.</p>

{{-- 11 --}}
<h2 class="page-break"><span class="section-num">11.</span> Jika Ditolak atau Diminta Melengkapi</h2>

<h3>Status &ldquo;Needs your input&rdquo; &mdash; permintaan dikembalikan</h3>
<div class="flow">Notifikasi masuk
    ↓
My requests → buka permintaan
    ↓
Baca pertanyaan atau catatan dari approver
    ↓
Lengkapi jawaban pada formulir
    ↓
Submit ulang
    ↓
Kembali ke antrean approval yang sama</div>

<h3>Status &ldquo;Rejected&rdquo; &mdash; permintaan ditolak</h3>
<p>Permintaan yang ditolak bersifat <strong>final</strong>: isinya tidak dapat diedit lalu dikirim ulang.
   Baca alasan penolakan pada detail permintaan. Bila kebutuhannya masih ada, buat permintaan baru dengan
   penjelasan yang sudah diperbaiki sesuai catatan tersebut.</p>

<h3>Membatalkan permintaan sendiri (Withdraw)</h3>
<p>Selama permintaan masih berstatus Draft, Menunggu approval department, Under review, atau Needs your input,
   tersedia tombol <strong>Withdraw</strong> pada halaman detail untuk menariknya. Setelah ditarik, permintaan
   tercatat sebagai ditutup dan tidak dapat dibuka kembali.</p>

<div class="confirm"><strong>Requires Confirmation:</strong> kebijakan perusahaan mengenai revisi setelah penolakan
    (cukup membuat permintaan baru, atau perlu koordinasi lebih dulu dengan ITD) sebaiknya ditegaskan oleh
    manajemen ITD.</div>

{{-- 12 --}}
<h2 class="page-break"><span class="section-num">12.</span> Notifikasi</h2>

<table class="data">
    <tr><th>Anda menerima notifikasi ketika</th><th>Tindakan yang diharapkan</th></tr>
    <tr><td>Permintaan Anda disetujui atau ditolak</td><td>Membaca catatan keputusan</td></tr>
    <tr><td>Permintaan dikembalikan untuk dilengkapi</td><td>Menjawab lalu submit ulang</td></tr>
    <tr><td>ITD mengajukan pertanyaan tentang pekerjaan Anda</td><td>Menjawab di <em>Waiting on me</em></td></tr>
    <tr><td>Pekerjaan selesai dan menunggu konfirmasi Anda</td><td>Konfirmasi sebelum batas waktu</td></tr>
    <tr><td>Batas waktu konfirmasi hampir habis</td><td>Segera konfirmasi agar permintaan tidak dibatalkan</td></tr>
    <tr><td>Permintaan Anda dijadwalkan atau dinyatakan selesai</td><td>Tidak ada &mdash; sebagai informasi</td></tr>
</table>

<p>Notifikasi tampil pada ikon lonceng di dalam aplikasi dan dikirim ke email Anda. Bagi Manager/Coordinator,
   notifikasi juga muncul saat ada permintaan department yang menunggu persetujuan.</p>

{{-- 13 --}}
<h2><span class="section-num">13.</span> Tombol Penting</h2>

<table class="data">
    <tr><th>Tombol</th><th>Fungsinya</th></tr>
    <tr><td><strong>Submit</strong></td><td>Mengirim permintaan ke approver &mdash; setelah ini isian tidak dapat diubah bebas</td></tr>
    <tr><td><strong>Send</strong></td><td>Mengirim permintaan Supporting ke antrean bantuan ITD</td></tr>
    <tr><td><strong>Withdraw</strong></td><td>Menarik permintaan yang belum diputuskan</td></tr>
    <tr><td><strong>Submit ulang</strong></td><td>Mengirim kembali permintaan yang berstatus Needs your input</td></tr>
    <tr><td><strong>Approve</strong></td><td>(Manager/Coordinator) Menyetujui dan meneruskan ke ITD</td></tr>
    <tr><td><strong>Reject</strong></td><td>(Manager/Coordinator) Menolak disertai alasan</td></tr>
    <tr><td><strong>Minta info</strong></td><td>(Manager/Coordinator) Mengembalikan ke pembuat untuk dilengkapi</td></tr>
    <tr><td><strong>Looks correct</strong></td><td>Menyatakan hasil pekerjaan sudah sesuai</td></tr>
    <tr><td><strong>Changes required</strong></td><td>Menyatakan hasil belum sesuai, disertai catatan perbaikan</td></tr>
    <tr><td><strong>Send my answer</strong></td><td>Mengirim jawaban atas pertanyaan ITD</td></tr>
    <tr><td><strong>Attach files</strong></td><td>Melampirkan berkas pendukung pada jawaban</td></tr>
</table>

{{-- 14 --}}
<h2 class="page-break"><span class="section-num">14.</span> Pertanyaan yang Sering Ditanyakan</h2>

<h4>Setelah saya Submit, permintaan saya pergi ke siapa?</h4>
<p>Ke Manager/Coordinator department Anda lebih dulu. Setelah disetujui, barulah masuk ke antrean Supervisor/Manager ITD.
   Bila jabatan Anda sendiri MGR atau COOR, permintaan langsung masuk ke ITD.</p>

<h4>Berapa lama sampai dikerjakan?</h4>
<p>Setelah disetujui ITD, permintaan mendapat jadwal berdasarkan kapasitas tim yang masih tersedia. Tanggal mulai dan
   target selesai dapat dilihat pada detail permintaan setelah statusnya menjadi Scheduled.</p>

<h4>Kenapa saya tidak bisa mengedit permintaan saya?</h4>
<p>Setelah Submit, permintaan berada di meja approver sehingga isinya dikunci agar yang dinilai tetap sama.
   Perubahan dapat Anda sampaikan bila permintaan dikembalikan dengan status Needs your input.</p>

<h4>Permintaan saya Rejected. Apa yang harus dilakukan?</h4>
<p>Buka detail permintaan, baca alasan penolakannya, lalu buat permintaan baru dengan penjelasan yang sudah diperbaiki
   bila kebutuhannya masih ada.</p>

<h4>Apa itu Waiting on me?</h4>
<p>Daftar hal yang ITD tunggu dari Anda: konfirmasi hasil pekerjaan (ada batas waktu &mdash; bila terlewat, permintaan
   dibatalkan otomatis) atau pertanyaan dari ITD (tanpa batas waktu, tetapi pekerjaan berhenti).</p>

<h4>Bisakah saya mengirim file Excel ke ITD?</h4>
<p>Bisa, melalui kolom <em>Attach files</em> saat menjawab di Waiting on me. Maksimal 5 berkas per jawaban.</p>

<h4>Kenapa saya tidak melihat menu Approvals?</h4>
<p>Menu tersebut hanya muncul untuk jabatan MGR atau COOR, dan hanya menampilkan permintaan dari department sendiri.</p>

<h4>Apa beda Feature, Project, dan Supporting?</h4>
<p><strong>Feature</strong> mengubah sistem yang sudah ada (satu approval ITD). <strong>Project</strong> membuat sesuatu
   yang baru (meeting dan dua tanda tangan ITD). <strong>Supporting</strong> adalah bantuan operasional di luar keduanya.</p>

<h4>Siapa PIC ITD saya?</h4>
<p>Anggota ITD yang ditunjuk saat permintaan disetujui. Namanya tercantum pada detail permintaan.</p>

<div class="note" style="margin-top:16pt;">
    <strong>Butuh bantuan?</strong> Hubungi tim ITD melalui kanal yang berlaku di perusahaan.<br>
    ELARA &mdash; Panduan Pengguna Edisi Requester &middot; Versi {{ $version }} &middot; {{ $generatedAt }} &middot; AIIA
</div>

{{-- Header dan nomor halaman untuk seluruh lembar. --}}
<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->getFont("DejaVu Sans", "normal");
        $grey = [0.58, 0.62, 0.68];
        $pdf->page_text(50, 22, "ELARA - Panduan Pengguna (Requester)", $font, 7.5, $grey);
        $pdf->page_text(50, 812, "AIIA - Untuk department non-ITD", $font, 7.5, $grey);
        $pdf->page_text(462, 812, "Halaman {PAGE_NUM} dari {PAGE_COUNT}", $font, 7.5, $grey);
    }
</script>

</body>
</html>
