<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>ELARA — User &amp; Business Flow Documentation</title>
    @include('docs._style')
</head>
<body>


{{-- 1. COVER --}}
<div class="cover">
    <div class="cover-logo">E</div>
    <div class="cover-badge">USER &amp; BUSINESS FLOW DOCUMENTATION</div>
    <h1>ELARA</h1>
    <p class="cover-sub">Platform pengelolaan permintaan IT, proyek, dan pekerjaan tim</p>
    <p class="cover-sub" style="margin-top: 14pt;"><strong>Perusahaan:</strong> AIIA</p>
    <p class="cover-sub"><strong>Alamat aplikasi:</strong> https://elara-qa.aiia.co.id</p>
    <p class="cover-sub"><strong>Versi dokumentasi:</strong> {{ $version }}</p>
    <p class="cover-sub"><strong>Tanggal:</strong> {{ $generatedAt }}</p>
    <p class="cover-meta">Dokumen ini ditujukan untuk pengguna awam.<br>Tidak memuat penjelasan teknis pengembangan.</p>
</div>

{{-- TABLE OF CONTENTS --}}
<div class="toc">
    <h2>Daftar Isi</h2>
    <table>
        @foreach ([
            ['1', 'Tentang Aplikasi'],
            ['2', 'Gambaran Umum Alur Sistem'],
            ['3', 'Daftar Role'],
            ['4', 'Hak Akses Setiap Role'],
            ['5', 'Role & Menu Matrix'],
            ['6', 'Department yang Terlibat'],
            ['7', 'Flow Setiap Department'],
            ['8', 'Flow Setiap Role'],
            ['9', 'End-to-End Business Flow'],
            ['10', 'Approval Flow'],
            ['11', 'Status Flow'],
            ['12', 'Penjelasan Setiap Menu'],
            ['13', 'Struktur Menu Aplikasi'],
            ['14', 'Panduan Penggunaan Berdasarkan Role'],
            ['15', 'Skenario Approve'],
            ['16', 'Skenario Reject dan Revision'],
            ['17', 'Monitoring Process'],
            ['18', 'Dashboard'],
            ['19', 'Master Data'],
            ['20', 'Form dan Input Utama'],
            ['21', 'Tombol dan Action'],
            ['22', 'Notification'],
            ['23', 'History'],
            ['24', 'Report'],
            ['25', 'Frequently Asked Questions'],
        ] as [$num, $title])
            <tr><td class="toc-num">{{ $num }}.</td><td>{{ $title }}</td></tr>
        @endforeach
    </table>
</div>

{{-- 2. TENTANG APLIKASI --}}
<div class="header-line">Elara · User Documentation · {{ $version }}</div>
<h2><span class="section-num">1.</span> Tentang Aplikasi</h2>

<h3>Aplikasi ini digunakan untuk apa?</h3>
<p><strong>Elara</strong> membantu perusahaan mengelola:</p>
<ul>
    <li><strong>Permintaan perubahan fitur</strong> pada sistem/aplikasi yang sudah dipakai sehari-hari.</li>
    <li><strong>Proposal proyek IT baru</strong> beserta studi kelayakan bisnis.</li>
    <li><strong>Permintaan dukungan operasional</strong> (printer, akun, jaringan, dll.).</li>
    <li><strong>Perencanaan, penjadwalan, dan pelaksanaan pekerjaan IT</strong> setelah permintaan disetujui.</li>
</ul>

<h3>Tujuan utama</h3>
<ul>
    <li>Semua permintaan ke IT tercatat rapi, tidak hilang di chat atau email.</li>
    <li>Setiap permintaan melewati approval yang jelas (department → ITD).</li>
    <li>Requester dapat memantau status tanpa harus menanyakan manual ke IT.</li>
    <li>Tim ITD dapat merencanakan kapasitas, menjadwalkan, dan melacak pekerjaan.</li>
</ul>

<h3>Proses bisnis yang dibantu</h3>
<ul>
    <li>Pengajuan fitur baru / perubahan sistem (Feature Request).</li>
    <li>Pengajuan proyek IT (Project Request) dengan meeting scoping dan dua tanda tangan ITD.</li>
    <li>Penanganan pekerjaan pendukung (Supporting Request).</li>
    <li>Eksekusi task, validasi ke requester, hingga status selesai (Delivered).</li>
</ul>

<h3>Siapa pengguna utama?</h3>
<table class="data">
    <tr><th>Kelompok</th><th>Siapa</th><th>Peran dalam aplikasi</th></tr>
    <tr><td>Requester</td><td>Karyawan dari department selain ITD</td><td>Mengajukan permintaan &amp; memantau progress</td></tr>
    <tr><td>Manager / Coordinator Department</td><td>MGR atau COOR di department pengaju</td><td>Approval tingkat department</td></tr>
    <tr><td>Tim ITD</td><td>Supervisor, Manager, Member ITD</td><td>Review, approval, scheduling, eksekusi</td></tr>
    <tr><td>Admin / Owner workspace</td><td>Pengelola workspace ITD</td><td>Pengaturan master data &amp; anggota</td></tr>
</table>

<div class="note"><strong>Catatan tampilan:</strong> Di layar login, sidebar, notifikasi, dan dokumen, nama produk yang tampil adalah <strong>Elara</strong>.</div>

{{-- 3. GAMBARAN UMUM --}}
<h2 class="page-break"><span class="section-num">2.</span> Gambaran Umum Alur Sistem</h2>

<p>Elara memiliki <strong>dua area kerja</strong> yang berbeda tergantung role Anda:</p>
<table class="data">
    <tr><th>Area</th><th>Untuk siapa</th><th>Fungsi</th></tr>
    <tr><td><strong>Request Desk</strong> (Meja Permintaan)</td><td>Requester &amp; Manager department</td><td>Buat &amp; pantau permintaan</td></tr>
    <tr><td><strong>Delivery Desk</strong> (Meja ITD)</td><td>Supervisor, Manager, Member ITD</td><td>Approval, task, jadwal, eksekusi</td></tr>
</table>

<h3>Alur Feature Request (ringkas)</h3>
<div class="flow">Karyawan department (Requester)
    ↓  Isi form &amp; Submit
Manager/Coordinator department (jika diperlukan)
    ↓  Approve / Reject / Minta info
Supervisor atau Manager ITD
    ↓  Approve (+ tentukan PIC &amp; estimasi jam) / Reject / Minta info
Penjadwalan otomatis
    ↓  Scheduled → In Progress
Tim ITD mengerjakan task
    ↓  Validasi ke requester (jika perlu)
Delivered (Selesai)</div>

<h3>Alur Project Request (ringkas)</h3>
<div class="flow">Requester mengajukan proposal proyek
    ↓  Approval department (jika diperlukan)
Supervisor ITD — meeting scoping
    ↓  Tanda tangan Supervisor ITD
    ↓  Tanda tangan Manager ITD
Approved → Scheduled → In Progress → Delivered</div>

<h3>Alur Supporting Request (ringkas)</h3>
<div class="flow">Requester mengirim permintaan dukungan
    ↓  Masuk antrian ITD (Supporting)
Tim ITD mengerjakan
    ↓  To Do → In Progress → Completed / Cancelled</div>

{{-- 4. DAFTAR ROLE --}}
<h2 class="page-break"><span class="section-num">3.</span> Daftar Role</h2>

<p>Role di Elara berasal dari <strong>workspace membership</strong> dan <strong>jabatan organisasi</strong> (direktori perusahaan). Role berikut benar-benar ada di aplikasi:</p>

<table class="data">
    <tr><th>Role di Elara</th><th>Department / Konteks</th><th>Fungsi Utama</th></tr>
    <tr><td><strong>Requester</strong></td><td>Semua department kecuali ITD</td><td>Membuat &amp; memantau permintaan (feature, project, supporting)</td></tr>
    <tr><td><strong>Manager Department</strong> (MGR/COOR)</td><td>Department masing-masing</td><td>Approval permintaan dari bawahannya sebelum ke ITD</td></tr>
    <tr><td><strong>Supervisor ITD</strong> (SPV/SCH)</td><td>ITD</td><td>Review &amp; approve feature; meeting &amp; tanda tangan pertama project</td></tr>
    <tr><td><strong>Manager ITD</strong> (MGR/COOR di ITD)</td><td>ITD</td><td>Approve feature; tanda tangan kedua project; oversight delivery</td></tr>
    <tr><td><strong>Member ITD</strong> (STF/LDR/dll.)</td><td>ITD</td><td>Mengerjakan task, update progress, validasi</td></tr>
    <tr><td><strong>Viewer</strong></td><td>Workspace ITD</td><td>Hanya melihat (tidak mengubah data)</td></tr>
    <tr><td><strong>Admin</strong></td><td>Workspace ITD</td><td>Mengelola anggota, master data, integrasi</td></tr>
    <tr><td><strong>Owner</strong></td><td>Workspace ITD</td><td>Pemilik workspace, akses penuh</td></tr>
</table>

<div class="note"><strong>Role project (di dalam satu proyek):</strong> Leader (Manager), Member, Viewer — mengatur siapa yang boleh mengubah task di proyek tertentu.</div>

{{-- 5. HAK AKSES --}}
<h2 class="page-break"><span class="section-num">4.</span> Hak Akses Setiap Role</h2>

<h3>Requester (Karyawan Department)</h3>
<p><strong>Dapat:</strong></p>
<ul>
    <li>Login ke Request Desk.</li>
    <li>Membuat Feature Request, Project Request, Supporting Request.</li>
    <li>Melihat permintaan sendiri &amp; permintaan department (feature).</li>
    <li>Memantau Timeline IT (read-only).</li>
    <li>Menjawab pertanyaan IT di menu <em>Waiting on me</em>.</li>
    <li>Menarik (withdraw) permintaan yang masih terbuka.</li>
    <li>Mengirim ulang setelah diminta informasi tambahan.</li>
</ul>
<p><strong>Tidak dapat:</strong></p>
<ul>
    <li>Approve permintaan ITD.</li>
    <li>Mengakses Delivery Desk (task board, schedule internal penuh).</li>
    <li>Mengubah master data systems.</li>
</ul>

<h3>Manager / Coordinator Department (MGR/COOR)</h3>
<p>Selain hak Requester di atas, jika jabatan Anda MGR/COOR di department yang sama:</p>
<ul>
    <li>Melihat menu <strong>Approvals</strong> di Request Desk.</li>
    <li>Approve / Reject / Minta info untuk permintaan dari department sendiri.</li>
    <li>Menunjuk pengganti approval (delegation) jika fitur delegation aktif.</li>
</ul>
<p><strong>Tidak dapat:</strong> Approve permintaan department lain; approve tingkat ITD.</p>

<h3>Supervisor ITD</h3>
<ul>
    <li>Akses Delivery Desk penuh (kecuali viewer-only actions).</li>
    <li>Review &amp; decide Feature Request (Approve/Reject/Needs info).</li>
    <li>Menjalankan meeting scoping Project Request.</li>
    <li>Tanda tangan Supervisor pada Project Request.</li>
    <li>Mengelola task, schedule, supporting work.</li>
    <li>Menerima &amp; meneruskan proposed task plan (AI breakdown).</li>
</ul>

<h3>Manager ITD</h3>
<ul>
    <li>Semua hak Supervisor ITD.</li>
    <li>Tanda tangan Manager pada Project Request (setelah Supervisor).</li>
    <li>Melihat performance report &amp; export.</li>
</ul>

<h3>Member ITD</h3>
<ul>
    <li>Mengerjakan task yang ditugaskan.</li>
    <li>Update status task, upload file, komentar.</li>
    <li>Membuat supporting task internal.</li>
</ul>
<p><strong>Tidak dapat:</strong> Approve feature/project request (kecuali juga memegang role Supervisor/Manager).</p>

<h3>Viewer ITD</h3>
<ul>
    <li>Melihat project, task, dashboard.</li>
    <li><strong>Tidak dapat</strong> membuat atau mengubah task.</li>
</ul>

<h3>Admin / Owner ITD</h3>
<ul>
    <li>Semua akses delivery + Settings workspace, Master data, Integrations, kelola anggota.</li>
</ul>

{{-- 6. ROLE MENU MATRIX --}}
<h2 class="page-break"><span class="section-num">5.</span> Role &amp; Menu Matrix</h2>

<p>Keterangan: <span class="ok">✓</span> = dapat akses · <span class="no">—</span> = tidak ada akses</p>

<h3>Request Desk (Meja Permintaan)</h3>
<table class="data">
    <tr>
        <th>Menu / Fitur</th>
        <th>Requester</th>
        <th>MGR Dept</th>
        <th>Supervisor ITD*</th>
    </tr>
    <tr><td>Timeline IT</td><td class="ok">✓</td><td class="ok">✓</td><td class="no">—</td></tr>
    <tr><td>My requests</td><td class="ok">✓</td><td class="ok">✓</td><td class="no">—</td></tr>
    <tr><td>Waiting on me</td><td class="ok">✓</td><td class="ok">✓</td><td class="no">—</td></tr>
    <tr><td>Department Approvals</td><td class="no">—</td><td class="ok">✓</td><td class="no">—</td></tr>
    <tr><td>Buat Feature Request</td><td class="ok">✓</td><td class="ok">✓</td><td class="no">—</td></tr>
    <tr><td>Buat Project Request</td><td class="ok">✓</td><td class="ok">✓</td><td class="no">—</td></tr>
    <tr><td>Buat Supporting Request</td><td class="ok">✓</td><td class="ok">✓</td><td class="no">—</td></tr>
</table>
<p style="font-size:8pt;color:#64748b;">* Supervisor ITD yang juga punya membership Requester di department lain dapat membuka Request Desk untuk department tersebut.</p>

<h3>Delivery Desk (Meja ITD)</h3>
<table class="data">
    <tr>
        <th>Menu / Fitur</th>
        <th>Member</th>
        <th>Supervisor</th>
        <th>Manager</th>
        <th>Admin/Owner</th>
        <th>Viewer</th>
    </tr>
    <tr><td>Home (Dashboard)</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td></tr>
    <tr><td>Ask AI</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td></tr>
    <tr><td>Approvals (ITD)</td><td class="no">—</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="no">—</td></tr>
    <tr><td>My tasks</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">*</td></tr>
    <tr><td>Schedule</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td></tr>
    <tr><td>Features / Systems</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td></tr>
    <tr><td>Supporting (internal)</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td></tr>
    <tr><td>Projects</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td></tr>
    <tr><td>Team</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td></tr>
    <tr><td>Performance / Report</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td></tr>
    <tr><td>Messages</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td><td class="ok">✓</td></tr>
    <tr><td>Master data</td><td class="no">—</td><td class="no">—</td><td class="no">—</td><td class="ok">✓</td><td class="no">—</td></tr>
    <tr><td>Workspace settings</td><td class="no">—</td><td class="no">—</td><td class="no">—</td><td class="ok">✓</td><td class="no">—</td></tr>
</table>
<p style="font-size:8pt;color:#64748b;">* Viewer: lihat saja, tidak bisa mengubah task.</p>

{{-- 7. DEPARTMENT --}}
<h2 class="page-break"><span class="section-num">6.</span> Department yang Terlibat</h2>

<p>Department di Elara diambil dari <strong>direktori organisasi perusahaan</strong> (bukan daftar hard-coded). Setiap department non-ITD memiliki workspace sendiri untuk requester.</p>

<h3>ITD — Information Technology Department</h3>
<p><strong>Fungsi:</strong> Pusat delivery. Menerima permintaan yang sudah lolos approval department, merencanakan, menjadwalkan, dan mengeksekusi pekerjaan.</p>
<p><strong>Role:</strong> Supervisor, Manager, Member, Admin, Owner, Viewer.</p>
<p><strong>Aktivitas:</strong> Approval ITD, breakdown task, scheduling, development/support, validasi ke requester.</p>

<h3>Department Pengaju (contoh: Produksi, Quality, Accounting, Maintenance, PPIC, dll.)</h3>
<p><strong>Fungsi:</strong> Mengajukan kebutuhan IT terkait sistem yang dipakai di department tersebut.</p>
<p><strong>Role:</strong> Requester (semua karyawan); Manager/Coordinator untuk approval department.</p>
<p><strong>Aktivitas:</strong> Buat request → Submit → Pantau status → Jawab pertanyaan IT → Terima hasil.</p>

<div class="confirm"><strong>Requires Confirmation:</strong> Daftar lengkap kode department (mis. FIN, QA, PRD) mengikuti data direktori organisasi perusahaan saat deployment. Hubungi administrator HR/IT untuk daftar resmi department yang terdaftar.</div>

{{-- 8. FLOW DEPARTMENT --}}
<h2 class="page-break"><span class="section-num">7.</span> Flow Setiap Department</h2>

<h3>Department non-ITD (contoh: Produksi)</h3>
<div class="flow">Staff Produksi (Requester)
    ↓  Login Request Desk
    ↓  Pilih Feature / Project / Supporting
    ↓  Isi form lengkap
    ↓  Submit
    ↓  [Jika bukan Manager] Menunggu Manager Produksi
Manager Produksi
    ↓  Buka Approvals → Review
    ↓  Approve → diteruskan ke ITD
    ↓  Reject / Minta info → kembali ke requester
ITD
    ↓  Review → Schedule → Kerjakan → Delivered
Requester
    ↓  Pantau di My requests &amp; Timeline</div>

<h3>Department ITD</h3>
<div class="flow">Karyawan ITD login ke Delivery Desk
    ↓  Tidak mengajukan feature ke diri sendiri via Request Desk*
    ↓  Bekerja lewat task board &amp; schedule
Supervisor/Manager ITD
    ↓  Memproses antrian Approvals
    ↓  Menetapkan PIC &amp; estimasi jam saat approve feature</div>
<p style="font-size:9pt;">* Jika karyawan ITD perlu mengajukan permintaan sebagai user department lain, dibutuhkan membership Requester terpisah — <strong>Requires Confirmation</strong> dengan administrator.</p>

<h3>Kapan approval department dilewati?</h3>
<ul>
    <li>Pengaju sudah berjabatan <strong>Manager/Coordinator (MGR/COOR)</strong> di department-nya, atau</li>
    <li>Pengaju berasal dari department <strong>ITD</strong> (langsung ke review ITD).</li>
</ul>

{{-- 9. FLOW ROLE --}}
<h2 class="page-break"><span class="section-num">8.</span> Flow Setiap Role</h2>

<h3>Role: Requester</h3>
<div class="flow">1. Login (email + password organisasi)
2. Masuk Request Desk → My requests
3. Klik Feature / Project / Supporting
4. Isi form (wajib lengkap)
5. Submit
6. Status: Menunggu approval department ATAU langsung Under review (ITD)
7. Pantau notifikasi &amp; halaman detail request
8. Jika Needs your input → isi jawaban → submit ulang
9. Jika Delivered → cek aplikasi &amp; history</div>

<h3>Role: Manager Department</h3>
<div class="flow">1. Login Request Desk
2. Buka Approvals (badge merah jika ada antrian)
3. Buka detail permintaan dari department
4. Baca current/target/benefit atau proposal proyek
5. Pilih: Setujui / Tolak / Minta info (+ catatan wajib untuk tolak/minta info)
6. Pantau permintaan lanjut ke ITD</div>

<h3>Role: Supervisor ITD</h3>
<div class="flow">1. Login Delivery Desk
2. Approvals → tab Feature / Project / Proposed plans
3. Review permintaan (urgent di urutkan lebih dulu)
4. Feature: Approve (+ PIC + estimasi jam) / Reject / Ask for detail
5. Project: Jadwalkan meeting → tanda tangan SPV → lanjut ke Manager
6. Terima proposed plan task jika ada
7. Kerjakan &amp; update task di Features/Projects</div>

<h3>Role: Member ITD</h3>
<div class="flow">1. Login Delivery Desk
2. My tasks → buka task yang ditugaskan
3. Update status, checklist, file, komentar
4. Jika perlu konfirmasi requester → buat validation checkpoint
5. Selesaikan task hingga request status Delivered</div>

{{-- 10. END TO END --}}
<h2 class="page-break"><span class="section-num">9.</span> End-to-End Business Flow</h2>

<h3>Feature Request — perjalanan lengkap</h3>
<div class="flow">[Requester] Submit permintaan fitur
        ↓
[Manager Dept] Approve? — Tidak → Rejected (requester revisi*)
                — Minta info → Needs your input → requester jawab → kembali ke Manager
                — Ya ↓
[Supervisor/Manager ITD] Under review
        ↓ Approve (+ PIC IT + estimasi jam kerja)
[Planner] Scheduled (jadwal otomatis berdasarkan kapasitas)
        ↓
[Member ITD] In Progress (task dikerjakan)
        ↓ Validasi ke requester jika perlu (Waiting on me)
[Requester] Konfirmasi hasil
        ↓
Delivered — SELESAI

*Setelah Rejected, requester tidak bisa edit otomatis — buat permintaan baru atau hubungi ITD.
  Requires Confirmation: kebijakan revisi pasca-reject di lapangan.</div>

<h3>Project Request — perjalanan lengkap</h3>
<div class="flow">Requester → [Manager Dept] → Pending meeting (ITD)
→ Meeting scoping → Pending SPV sign → Pending Manager sign
→ Approved → Scheduled → In Progress → Delivered</div>

<p><strong>Pertanyaan kunci terjawab:</strong> <em>"Setelah saya submit, data saya pergi ke siapa?"</em></p>
<ol>
    <li>Jika Anda staff biasa → <strong>Manager/Coordinator department Anda</strong> (jika department bukan ITD).</li>
    <li>Setelah department approve → <strong>Supervisor/Manager ITD</strong>.</li>
    <li>Setelah ITD approve → <strong>PIC ITD</strong> yang ditunjuk + scheduler menempatkan pekerjaan.</li>
</ol>

{{-- 11. APPROVAL FLOW --}}
<h2 class="page-break"><span class="section-num">10.</span> Approval Flow</h2>

<h3>Feature Request</h3>
<table class="data">
    <tr><th>Langkah</th><th>Pelaku</th><th>Hasil jika Approve</th><th>Hasil jika Reject</th><th>Minta info</th></tr>
    <tr><td>1</td><td>Requester</td><td colspan="3">Submit → status Pending department ATAU Pending review</td></tr>
    <tr><td>2</td><td>Manager Dept</td><td>→ Pending review (ITD)</td><td>→ Rejected</td><td>→ Needs your input</td></tr>
    <tr><td>3</td><td>Supervisor/Manager ITD</td><td>→ Approved → Scheduled</td><td>→ Rejected</td><td>→ Needs your input</td></tr>
</table>

<h3>Project Request</h3>
<table class="data">
    <tr><th>Langkah</th><th>Pelaku</th><th>Keterangan</th></tr>
    <tr><td>1</td><td>Requester</td><td>Submit proposal</td></tr>
    <tr><td>2</td><td>Manager Dept</td><td>Opsional — sama seperti feature</td></tr>
    <tr><td>3</td><td>Supervisor ITD</td><td>Meeting scoping wajib sebelum tanda tangan SPV</td></tr>
    <tr><td>4</td><td>Supervisor ITD</td><td>Tanda tangan pertama (Pending SPV → Pending Manager)</td></tr>
    <tr><td>5</td><td>Manager ITD</td><td>Tanda tangan kedua — harus orang berbeda dari SPV</td></tr>
    <tr><td>6</td><td>Planner</td><td>Approved → Scheduled → In Progress → Delivered</td></tr>
</table>

<div class="flow">User Submit
    ↓
Manager Department
    ├── Approve → ITD Review
    ├── Reject → Rejected (baca catatan)
    └── Minta info → Needs your input → User jawab → Submit ulang
    ↓
ITD Review
    ├── Approve → Scheduling
    ├── Reject → Rejected
    └── Minta info → Needs your input</div>

<p><strong>Catatan reject &amp; revisi:</strong> Reject dan Minta info <strong>wajib disertai catatan/alasan</strong>. Minta info mengembalikan request ke requester; setelah dijawab, request kembali ke tahap approval yang sama (department atau ITD).</p>

{{-- 12. STATUS FLOW --}}
<h2 class="page-break"><span class="section-num">11.</span> Status Flow</h2>

<h3>Status Feature Request &amp; Project Request</h3>
<table class="data">
    <tr><th>Status (EN)</th><th>Artinya</th><th>Apa yang harus dilakukan pengguna</th></tr>
    <tr><td>Draft</td><td>Belum terkirim</td><td>Lengkapi &amp; submit (form langsung submit saat dibuat)</td></tr>
    <tr><td>Menunggu approval department</td><td>Menunggu Manager dept</td><td>Requester menunggu; Manager buka Approvals</td></tr>
    <tr><td>Under review</td><td>ITD sedang review</td><td>Requester menunggu; ITD buka Approvals</td></tr>
    <tr><td>Needs your input</td><td>Butuh jawaban Anda</td><td>Requester baca catatan &amp; kirim jawaban</td></tr>
    <tr><td>Approved</td><td>Disetujui ITD</td><td>Menunggu penjadwalan otomatis</td></tr>
    <tr><td>Scheduled</td><td>Sudah dijadwalkan</td><td>Pantau Timeline / detail request</td></tr>
    <tr><td>In progress</td><td>Sedang dikerjakan</td><td>Pantau progress; jawab validasi jika ada</td></tr>
    <tr><td>Delivered</td><td>Selesai</td><td>Cek hasil di aplikasi; tidak perlu tindakan</td></tr>
    <tr><td>Rejected</td><td>Ditolak</td><td>Baca alasan; ajukan ulang jika perlu</td></tr>
    <tr><td>Taken down</td><td>Ditarik/dibatalkan ITD</td><td>Hubungi ITD untuk penjelasan</td></tr>
</table>

<h3>Status tambahan Project Request</h3>
<table class="data">
    <tr><th>Status</th><th>Artinya</th></tr>
    <tr><td>Awaiting scoping meeting</td><td>Menunggu meeting dengan ITD</td></tr>
    <tr><td>Needs supervisor approval</td><td>Menunggu tanda tangan Supervisor ITD</td></tr>
    <tr><td>Needs manager approval</td><td>Menunggu tanda tangan Manager ITD</td></tr>
</table>

<h3>Status Supporting Request (ITD internal view)</h3>
<table class="data">
    <tr><th>Status</th><th>Artinya</th></tr>
    <tr><td>To Do</td><td>Belum dikerjakan</td></tr>
    <tr><td>In Progress</td><td>Sedang dikerjakan</td></tr>
    <tr><td>Completed</td><td>Selesai</td></tr>
    <tr><td>Cancelled</td><td>Dibatalkan</td></tr>
</table>

<h3>Diagram status Feature Request</h3>
<div class="flow">Draft
    ↓ Submit
Pending department ──→ Rejected
    ↓ Approve dept
Pending review ──→ Rejected
    ↓ Approve ITD      ↘ Needs info → (jawab) → kembali review/dept
Approved
    ↓
Scheduled
    ↓
In progress
    ↓
Delivered ✓</div>

{{-- 13. MENU --}}
<h2 class="page-break"><span class="section-num">12.</span> Penjelasan Setiap Menu</h2>

<h3>Request Desk</h3>

<h4>Timeline</h4>
<p><strong>Fungsi:</strong> Melihat ringkasan pekerjaan IT yang sedang berjalan (project/feature) tanpa detail internal.</p>
<p><strong>Digunakan oleh:</strong> Requester, Manager department.</p>
<p><strong>Hasil:</strong> Membantu requester memahami kapan pekerjaan IT dijadwalkan.</p>

<h4>My requests</h4>
<p><strong>Fungsi:</strong> Daftar semua permintaan Anda (Feature, Project, Supporting, History).</p>
<p><strong>Aktivitas:</strong> View detail, filter status, withdraw, jawab needs-info.</p>

<h4>Waiting on me</h4>
<p><strong>Fungsi:</strong> Semua hal yang membuat ITD berhenti menunggu Anda. Isinya <strong>dua jenis</strong>:</p>
<table class="data">
    <tr><th>Jenis</th><th>Muncul kapan</th><th>Yang Anda lakukan</th><th>Jika didiamkan</th></tr>
    <tr>
        <td><strong>Konfirmasi hasil</strong><br><span style="font-size:8pt;color:#64748b;">badge hitung mundur</span></td>
        <td>ITD menyelesaikan pekerjaan yang hasilnya hanya bisa dinilai oleh Anda</td>
        <td>Pilih <em>Looks correct</em> atau <em>Changes required</em> + catatan</td>
        <td>Lewat tanggal batas &rarr; permintaan <strong>dibatalkan otomatis</strong> dan jadwal ITD dialihkan</td>
    </tr>
    <tr>
        <td><strong>Pertanyaan dari ITD</strong><br><span style="font-size:8pt;color:#64748b;">badge &ldquo;Question from ITD&rdquo;</span></td>
        <td>ITD butuh data/keterangan untuk melanjutkan sebuah task (mis. daftar part number)</td>
        <td>Tulis jawaban pada kolom <em>Your answer</em></td>
        <td>Tidak ada pembatalan otomatis &mdash; tetapi pekerjaan ITD berhenti menunggu Anda</td>
    </tr>
</table>
<p><strong>Melampirkan berkas:</strong> pada kedua jenis di atas tersedia kolom <em>Attach files</em> &mdash; maksimal 5 berkas
   (Excel, Word, PDF, gambar, atau zip). Berkas langsung menempel pada pekerjaan ITD yang bersangkutan, jadi tidak perlu
   dikirim lewat jalur lain.</p>

<h4>Department Approvals</h4>
<p><strong>Fungsi:</strong> Antrian approval untuk Manager/Coordinator department.</p>
<p><strong>Aktivitas:</strong> Approve, Reject, Minta info.</p>

<h3>Delivery Desk</h3>

<h4>Home (Dashboard)</h4>
<p><strong>Fungsi:</strong> Ringkasan task, timeline, aktivitas terbaru, meeting hari ini.</p>
<p><strong>KPI:</strong> Total task, In progress, Overdue, Completed.</p>

<h4>Approvals</h4>
<p><strong>Fungsi:</strong> Antrian permintaan dari luar ITD + proposed task plans.</p>
<p><strong>Aktivitas:</strong> Klik baris pada tabel antrian &rarr; halaman permintaan lengkap &rarr; Review, Approve
   (isi <em>Working hours</em>, PIC, dan &mdash; bila perlu &mdash; tanggal mulai/selesai manual), Reject, atau Ask for more detail.</p>
<p><strong>Tanggal pengerjaan:</strong> jika kolom tanggal dikosongkan, sistem yang menentukan jadwal dari kapasitas tim yang
   masih tersedia. Jika diisi, tanggal itulah yang dipakai dan tidak akan digeser sistem.</p>
<p><strong>Bertanya ke requester:</strong> pada halaman task tersedia panel <em>Ask the requester</em> untuk meminta data
   tambahan; pertanyaan tersebut muncul di menu <em>Waiting on me</em> milik requester.</p>

<h4>My tasks</h4>
<p><strong>Fungsi:</strong> Task yang ditugaskan kepada Anda.</p>

<h4>Schedule</h4>
<p><strong>Fungsi:</strong> Kalender meeting &amp; aktivitas berbasis waktu.</p>

<h4>Features (Systems)</h4>
<p><strong>Fungsi:</strong> Daftar sistem yang dirawat ITD dan backlog fitur per sistem.</p>

<h4>Supporting</h4>
<p><strong>Fungsi:</strong> Antrian pekerjaan dukungan operasional dari requester.</p>

<h4>Projects</h4>
<p><strong>Fungsi:</strong> Proyek IT (termasuk proyek hasil approved project request).</p>

<h4>Performance</h4>
<p><strong>Fungsi:</strong> Laporan performa tim dengan filter &amp; export CSV/PDF.</p>

<h4>Messages</h4>
<p><strong>Fungsi:</strong> Percakapan tim dalam workspace.</p>

<h4>Settings → Master data</h4>
<p><strong>Fungsi:</strong> Systems, Task categories, Workflow (status templates).</p>
<p><strong>Digunakan oleh:</strong> Admin/Owner saja.</p>

{{-- 14. STRUKTUR MENU --}}
<h2 class="page-break"><span class="section-num">13.</span> Struktur Menu Aplikasi</h2>

<h3>Request Desk</h3>
<div class="flow">Timeline
My requests
Waiting on me
Approvals (Manager dept saja)

Raise something new:
  ├── Feature
  ├── Project
  └── Supporting</div>

<h3>Delivery Desk</h3>
<div class="flow">Home
Ask AI
Approvals

Work
  ├── My tasks
  ├── Schedule
  ├── Features (Systems)
  └── Supporting

Projects
  ├── [Daftar proyek]
  └── All projects

Team
  ├── [Bawahan langsung — jika ada]
  └── All team

More
  ├── Performance
  └── Messages

Settings
  ├── Profile
  ├── Security
  ├── Notifications
  ├── Workspace (Admin)
  ├── Master data (Admin)
  └── Integrations (Admin)</div>

{{-- 15. PANDUAN ROLE --}}
<h2 class="page-break"><span class="section-num">14.</span> Panduan Penggunaan Berdasarkan Role</h2>

<h3>Panduan Requester</h3>
<ol>
    <li><strong>Login:</strong> Buka <strong>https://elara-qa.aiia.co.id</strong>, masukkan email &amp; password akun organisasi (akun yang sama dengan aplikasi perusahaan lain).</li>
    <li><strong>Buka menu:</strong> Sidebar kiri → pilih jenis permintaan (Feature/Project/Supporting).</li>
    <li><strong>Feature Request:</strong> Pilih sistem → isi Judul, Kondisi saat ini, Kondisi target, Benefit, Urgency → Submit.</li>
    <li><strong>Project Request:</strong> Isi overview, objectives, before/after, benefits, cost, ROI → Submit.</li>
    <li><strong>Supporting:</strong> Isi judul, detail, kategori, prioritas, needed by → Send.</li>
    <li><strong>Monitoring:</strong> My requests → klik baris → lihat timeline status &amp; catatan approval.</li>
    <li><strong>Needs info:</strong> Buka request → isi jawaban → submit ulang.</li>
    <li><strong>Validasi:</strong> Waiting on me → jawab pertanyaan IT atau konfirmasi pekerjaan selesai.</li>
    <li><strong>Withdraw:</strong> Tarik permintaan selama masih Draft/Pending/Needs info (tombol withdraw di detail).</li>
</ol>

<h3>Panduan Manager Department</h3>
<ol>
    <li>Login → Approvals (badge angka = jumlah antrian).</li>
    <li>Klik permintaan → baca detail lengkap.</li>
    <li>Pilih keputusan + isi catatan jika Reject/Minta info.</li>
    <li>Record decision → requester &amp; ITD mendapat notifikasi.</li>
</ol>

<h3>Panduan Supervisor/Manager ITD</h3>
<ol>
    <li>Login Delivery Desk → Approvals.</li>
    <li>Tab Feature: review → jika Approve, pilih IT PIC + estimasi jam (+ opsional tanggal).</li>
    <li>Tab Project: jadwalkan meeting → setelah meeting, tanda tangan berurutan SPV lalu Manager.</li>
    <li>Tab Proposed plans: terima/revisi rencana task dari AI breakdown.</li>
    <li>Pantau eksekusi di Features/Projects/My tasks.</li>
</ol>

{{-- 16-17 SKENARIO --}}
<h2 class="page-break"><span class="section-num">15.</span> Skenario Approve (Normal)</h2>

<div class="flow">Senin 09:00 — Budi (Staff QA) submit Feature Request "Export laporan NG"
Senin 10:00 — Manager QA approve department
Senin 14:00 — Supervisor ITD review &amp; approve
         → PIC: Andi (Member ITD), Estimasi: 16 jam
Senin 15:00 — Status Scheduled (jadwal otomatis)
Selasa–Jumat — Andi mengerjakan (In progress)
Jumat 16:00 — Validasi ke Budi (Waiting on me)
Jumat 17:00 — Budi konfirmasi OK
Jumat 17:05 — Status Delivered</div>

<p><strong>Yang dilihat Budi di setiap tahap:</strong></p>
<ul>
    <li>Setelah submit: "Menunggu approval department" atau "Under review".</li>
    <li>Setelah dept approve: notifikasi "Disetujui department".</li>
    <li>Setelah ITD approve: status Approved → Scheduled.</li>
    <li>Saat dikerjakan: In progress + muncul di Timeline.</li>
    <li>Selesai: Delivered + notifikasi.</li>
</ul>

<h2 class="page-break"><span class="section-num">16.</span> Skenario Reject dan Revision</h2>

<div class="flow">User Submit
    ↓
Manager Review → Reject ("Benefit belum jelas")
    ↓
User lihat status Rejected + catatan
    ↓
User buat permintaan baru yang lebih lengkap*
    ↓
Submit ulang → alur approval dari awal

Alternatif — Minta info:
Manager/ITD → Needs your input ("Lampirkan contoh laporan")
    ↓
User buka detail → jawab → submit
    ↓
Kembali ke Manager atau ITD (sesuai yang bertanya)</div>

<p>*Elara saat ini menutup alur pada status Rejected. Revisi praktis dilakukan dengan permintaan baru atau koordinasi ITD.</p>

{{-- 18 MONITORING --}}
<h2 class="page-break"><span class="section-num">17.</span> Monitoring Process</h2>

<p>Requester dapat memantau permintaan melalui:</p>
<ul>
    <li><strong>Status badge</strong> di My requests (warna menunjukkan urgency: hijau=selesai, merah=ditolak, kuning=menunggu).</li>
    <li><strong>Tab History</strong> untuk permintaan Delivered/Rejected/Withdrawn.</li>
    <li><strong>Detail request</strong> — timeline approval, catatan department &amp; ITD, reviewer name &amp; tanggal.</li>
    <li><strong>Timeline IT</strong> — posisi pekerjaan ITD secara visual (Gantt ringkas).</li>
    <li><strong>Waiting on me</strong> — jika ada tindakan dari Anda.</li>
    <li><strong>Notifikasi</strong> (ikon bell) — update status &amp; approval.</li>
</ul>

<table class="data">
    <tr><th>Informasi</th><th>Di mana melihat</th></tr>
    <tr><td>Status terkini</td><td>My requests / detail request</td></tr>
    <tr><td>Catatan approver</td><td>Detail request → bagian decision note</td></tr>
    <tr><td>Siapa yang approve</td><td>Detail request → department reviewer / IT reviewer</td></tr>
    <tr><td>Tanggal proses</td><td>Timeline di detail + kolom Submitted</td></tr>
    <tr><td>PIC ITD</td><td>Detail setelah ITD approve (assignee)</td></tr>
    <tr><td>Progress pekerjaan</td><td>Timeline IT + status In progress</td></tr>
</table>

{{-- 19 DASHBOARD --}}
<h2 class="page-break"><span class="section-num">18.</span> Dashboard</h2>

<h3>Request Desk — My requests (bukan dashboard KPI)</h3>
<p>Menampilkan greeting, tab Feature/Project/Supporting/History, filter status, jumlah per tab.</p>

<h3>Delivery Desk — Home Dashboard</h3>
<table class="data">
    <tr><th>Kartu KPI</th><th>Arti</th></tr>
    <tr><td>Total tasks</td><td>Semua task yang relevan dengan Anda</td></tr>
    <tr><td>Tasks in progress</td><td>Sedang dikerjakan</td></tr>
    <tr><td>Task overdue</td><td>Lewat deadline — perlu perhatian</td></tr>
    <tr><td>Completed tasks</td><td>Selesai dalam rentang tanggal filter</td></tr>
</table>
<p>Juga menampilkan: Project/Task timeline (Gantt), distribusi status, aktivitas terbaru, meeting mendatang, task hari ini.</p>

<h3>Approvals queue (ITD)</h3>
<p>Menampilkan jumlah antrian: Feature requests, Project requests, Proposed plans. Diurutkan urgent dulu, lalu yang paling lama menunggu.</p>

{{-- 20 MASTER DATA --}}
<h2 class="page-break"><span class="section-num">19.</span> Master Data</h2>

<table class="data">
    <tr><th>Master</th><th>Fungsi</th><th>Siapa akses</th><th>CRUD</th><th>Dipakai untuk</th></tr>
    <tr><td>Systems</td><td>Daftar aplikasi/sistem yang bisa diajukan feature request</td><td>Admin/Owner</td><td>Create/Edit</td><td>Form Feature Request</td></tr>
    <tr><td>Task categories</td><td>Label pengelompokan task</td><td>Admin/Owner</td><td>Create/Edit/Delete</td><td>Task di semua project</td></tr>
    <tr><td>Workflow (status templates)</td><td>Template status task untuk project baru</td><td>Admin/Owner</td><td>View/Edit</td><td>Project workflow</td></tr>
    <tr><td>Holidays</td><td>Hari libur untuk scheduler</td><td>Admin/Owner</td><td>Manage</td><td>Penjadwalan kapasitas</td></tr>
</table>

<p>Setiap System memiliki: nama, plant (body/unit/electric/office), department PIC, warna identitas.</p>

{{-- 21 FORM --}}
<h2 class="page-break"><span class="section-num">20.</span> Form dan Input Utama</h2>

<h3>Form Feature Request</h3>
<table class="data">
    <tr><th>Field</th><th>Wajib?</th><th>Penjelasan</th></tr>
    <tr><td>Which system?</td><td>Ya</td><td>Sistem yang ingin diubah</td></tr>
    <tr><td>Short title</td><td>Ya</td><td>Judul singkat permintaan</td></tr>
    <tr><td>Current condition</td><td>Ya (min. ~20 karakter)</td><td>Kondisi saat ini / masalah</td></tr>
    <tr><td>Target condition</td><td>Ya</td><td>Kondisi yang diharapkan</td></tr>
    <tr><td>Benefit</td><td>Ya</td><td>Manfaat bisnis</td></tr>
    <tr><td>Urgency</td><td>Ya</td><td>Low / Normal / High</td></tr>
</table>
<p><strong>Setelah Submit:</strong> Langsung masuk antrian approval (tidak ada Save Draft terpisah).</p>

<h3>Form Project Request</h3>
<p>Field wajib utama: Project name, Background, Why needed, Objectives (min. 1), Illustration, Before/After state, Benefits, Cost items, ROI. Target date opsional.</p>

<h3>Form Supporting Request</h3>
<p>Field wajib: Title, Details, Category (Hardware/Software/Network/Other), Priority, Needed by (opsional).</p>

<h3>Form Approval ITD (Feature)</h3>
<p>Saat Approve: wajib pilih <strong>IT PIC</strong> dan <strong>estimasi jam kerja</strong>. Tanggal start/due opsional (kosong = planner otomatis).</p>

{{-- 22 ACTIONS --}}
<h2 class="page-break"><span class="section-num">21.</span> Tombol dan Action</h2>

<table class="data">
    <tr><th>Action</th><th>Fungsi</th><th>Tersedia di</th></tr>
    <tr><td>Submit / Send</td><td>Kirim permintaan ke alur approval</td><td>Form request</td></tr>
    <tr><td>Approve</td><td>Setujui &amp; lanjutkan proses</td><td>Approval forms</td></tr>
    <tr><td>Reject</td><td>Tolak + wajib catatan</td><td>Approval forms</td></tr>
    <tr><td>Ask for more detail / Minta info</td><td>Minta klarifikasi ke requester</td><td>Approval forms</td></tr>
    <tr><td>Record decision</td><td>Simpan keputusan approval</td><td>ITD &amp; dept approval</td></tr>
    <tr><td>Withdraw</td><td>Tarik permintaan sendiri</td><td>Detail request (requester)</td></tr>
    <tr><td>Resubmit</td><td>Kirim ulang setelah needs-info</td><td>Detail request</td></tr>
    <tr><td>Download Guide</td><td>Unduh panduan PDF</td><td>Halaman create request</td></tr>
    <tr><td>Mark all read</td><td>Tandai semua notifikasi dibaca</td><td>Panel notifikasi</td></tr>
    <tr><td>Export CSV / PDF</td><td>Unduh laporan performa</td><td>Performance</td></tr>
    <tr><td>Sign out</td><td>Keluar aplikasi</td><td>Sidebar</td></tr>
</table>

<p><strong>Tidak tersedia</strong> di alur requester saat ini: Save Draft terpisah, Edit setelah submit, Delete request.</p>

{{-- 23 NOTIFICATION --}}
<h2 class="page-break"><span class="section-num">22.</span> Notification</h2>

<p>Elara mengirim notifikasi melalui:</p>
<ul>
    <li><strong>In-app</strong> (ikon bell — default aktif)</li>
    <li><strong>Email</strong> (opsional, diatur di Settings → Notifications)</li>
    <li><strong>Push browser</strong> (opsional)</li>
</ul>

<table class="data">
    <tr><th>Event</th><th>Siapa menerima</th><th>Kapan</th></tr>
    <tr><td>Feature requests and approvals</td><td>Manager dept, ITD, requester</td><td>Submit, approve, reject, needs info</td></tr>
    <tr><td>Project requests and approvals</td><td>Sama + peserta meeting</td><td>Tiap transisi status project request</td></tr>
    <tr><td>Validation checkpoint</td><td>Requester</td><td>ITD butuh konfirmasi / jawaban</td></tr>
    <tr><td>Proposed plans waiting</td><td>Supervisor/Manager ITD</td><td>AI breakdown siap direview</td></tr>
    <tr><td>Task assigned / status changed</td><td>Member ITD</td><td>Perubahan task</td></tr>
    <tr><td>Deadline reminders</td><td>Assignee task</td><td>Mendekati due date</td></tr>
</table>

<p><strong>Tindakan pengguna:</strong> Klik notifikasi → dibawa ke halaman terkait (detail request, approval, task).</p>

{{-- 24 HISTORY --}}
<h2 class="page-break"><span class="section-num">23.</span> History</h2>

<p><strong>Requester — tab History:</strong> Menyimpan permintaan Delivered, Rejected, dan Withdrawn.</p>
<p><strong>Informasi yang dicatat:</strong></p>
<ul>
    <li>Activity log setiap perubahan status (siapa, kapan, catatan).</li>
    <li>Department decision note &amp; IT decision note.</li>
    <li>Nama reviewer &amp; timestamp approval.</li>
    <li>Versi request (increment setiap transisi).</li>
</ul>
<p><strong>ITD — tab History di Approvals:</strong> Permintaan yang sudah diputuskan.</p>

{{-- 25 REPORT --}}
<h2 class="page-break"><span class="section-num">24.</span> Report</h2>

<h3>Performance Report (Delivery Desk)</h3>
<p><strong>Siapa:</strong> Semua member ITD workspace (data difilter sesuai akses project).</p>
<p><strong>Filter:</strong> Rentang tanggal, project, member, status, distribusi (status/priority).</p>
<p><strong>Informasi:</strong> KPI active projects, in progress, overdue, completed; grafik tren; heatmap beban anggota.</p>
<p><strong>Export:</strong> Tombol <strong>CSV</strong> dan <strong>PDF report</strong> di halaman Performance.</p>

{{-- 26 FAQ --}}
<h2 class="page-break"><span class="section-num">25.</span> Frequently Asked Questions</h2>

<h4>Permintaan saya status "Menunggu approval department" — artinya?</h4>
<p>Permintaan sudah terkirim dan menunggu Manager/Coordinator department Anda. Hubungi manager jika urgent.</p>

<h4>Saya sudah submit. Siapa yang menerima?</h4>
<p>Jika Anda staff biasa (bukan MGR/COOR) dan bukan dari ITD → Manager department Anda. Setelah itu → Supervisor/Manager ITD.</p>

<h4>Status "Needs your input" — apa yang harus saya lakukan?</h4>
<p>Buka detail permintaan, baca catatan approver, isi jawaban/kelengkapan, lalu kirim ulang.</p>

<h4>Permintaan Rejected — bisa diedit?</h4>
<p>Status Rejected bersifat final. Buat permintaan baru dengan perbaikan, atau hubungi ITD.</p>

<h4>Kenapa saya tidak bisa buat Feature Request?</h4>
<p>Kemungkinan: (1) Anda login sebagai tim ITD di Delivery Desk, bukan Requester; (2) belum ada sistem terdaftar di Master data; (3) profil organisasi belum lengkap.</p>

<h4>Apa beda Feature vs Project vs Supporting?</h4>
<p><strong>Feature</strong> = ubah sistem yang sudah ada (1 approval ITD). <strong>Project</strong> = usulan proyek baru (meeting + 2 tanda tangan ITD). <strong>Supporting</strong> = bantuan operasional di luar feature/project.</p>

<h4>Bagaimana tahu pekerjaan IT sudah selesai?</h4>
<p>Status berubah menjadi <strong>Delivered</strong> dan Anda mungkin diminta konfirmasi di <em>Waiting on me</em> sebelumnya.</p>

<h4>Apa itu Waiting on me?</h4>
<p>ITD menunggu respons Anda — bisa pertanyaan tentang request, atau konfirmasi hasil pekerjaan. Jika tidak dijawab sebelum tanggal deadline validation, pekerjaan dapat dibatalkan otomatis.</p>

<h4>Siapa PIC ITD?</h4>
<p>Orang ITD yang ditunjuk Supervisor/Manager saat approve feature request. Dialah yang bertanggung jawab mengerjakan/mengawal task.</p>

<div class="note" style="margin-top:16pt;">
    <strong>Dokumen ini</strong> dibuat otomatis dari alur aktual aplikasi Elara.<br>
    Versi {{ $version }} · {{ $generatedAt }} · AIIA<br>
    Untuk pertanyaan operasional, hubungi tim ITD atau administrator workspace.
</div>

{{-- Header dan nomor halaman untuk seluruh lembar. --}}
<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->getFont("DejaVu Sans", "normal");
        $grey = [0.58, 0.62, 0.68];
        $pdf->page_text(50, 22, "ELARA - User & Business Flow Documentation", $font, 7.5, $grey);
        $pdf->page_text(50, 812, "AIIA - Dokumentasi Pengguna", $font, 7.5, $grey);
        $pdf->page_text(462, 812, "Halaman {PAGE_NUM} dari {PAGE_COUNT}", $font, 7.5, $grey);
    }
</script>

</body>
</html>
