<?php

namespace Database\Seeders;

use App\Enums\FeatureRequestStatus;
use App\Enums\RequestUrgency;
use App\Models\FeatureRequest;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OrganizationDirectory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Feature requests carried over from Fiola: everything raised in 2026 that had not finished
 * when Orbitra took over. Requesters are matched to the organisation directory by the id the
 * old form recorded, and provisioned locally when they have never signed in here.
 */
class LegacyFeatureRequestSeeder extends Seeder
{
    /** Old "aplikasi" free text → the system name in the master list. */
    private const SYSTEMS = [
        'bela' => 'BELLA',
        'bella' => 'BELLA',
        'qira' => 'QIRA',
        'avicenna' => 'AVICENNA',
        'preman' => 'PREMAN',
        'digital henkaten' => 'DIGITAL HENKATEN',
        'https://henkaten.aiia.co.id' => 'DIGITAL HENKATEN',
        'tracebility electric' => 'TRACEBILITY - ELECTRIC',
        'tripillar' => 'TRIPILAR',
    ];

    /** Old final_status → where the request sits in Orbitra's flow. */
    private const STATUSES = [
        'manager approve' => FeatureRequestStatus::PENDING_REVIEW,
        // Legacy IT approval has no Orbitra estimate, reviewer, or AI plan. Put it back in
        // IT review so the current approval flow can collect those before scheduling it.
        'it approve' => FeatureRequestStatus::PENDING_REVIEW,
        'on progress' => FeatureRequestStatus::IN_PROGRESS,
    ];

    private const REQUESTS = [
        [
            'no_reg' => 'FTR/2605/001',
            'organization_user_id' => 2875,
            'fullname' => 'Marcellino Reyhan Ariputra',
            'system' => 'Bela',
            'title' => 'Time Record produksi (Problem, Reject In Line, Dandori, steuchi)',
            'problem' => 'Manual Input, Double input by Member & JP',
            'desired_outcome' => 'Realtime data, no double handling (Input Member yg di input JP)',
            'benefit' => 'Reduce MH input laporan produksi, Reduce Area in Line',
            'status' => 'On Progress',
            'created_at' => '2026-05-05 08:59:58',
            'updated_at' => '2026-05-08 10:23:32',
        ],
        [
            'no_reg' => 'FTR/2607/001',
            'organization_user_id' => 2820,
            'fullname' => 'Muhammad Ilham Baihaki',
            'system' => 'QIRA',
            'title' => 'Scrap money',
            'problem' => '1. Tidak ada fitur edit (jika salah masukin data tidak bisa di edit)
2. warna font putih (tidak terlihat)
3. list NG DC ROL tidak sesuai (karena beberapa ada NG MA ROL, AS ROL)',
            'desired_outcome' => '1. Ditambahkan ada fitur edit 
2. warna font hitam (agar terlihat)
3. List yang ada disini hanya untuk NG DC ROL (jika butuh data NG DC ROL bisa diinfokan ke quality)',
            'benefit' => 'Memudahkan user menggunakan dan update data di QIRA',
            'status' => 'IT Approve',
            'created_at' => '2026-07-06 11:36:29',
            'updated_at' => '2026-07-10 08:47:16',
        ],
        [
            'no_reg' => 'FTR/2606/007',
            'organization_user_id' => 678,
            'fullname' => 'Bagas Jati Wicaksono',
            'system' => 'Avicenna',
            'title' => 'Direct kanban customer',
            'problem' => 'Request perubahan direct kanban untuk CSH D05, before : kanban dowa vs kanban internal vs kanban customer',
            'desired_outcome' => 'after : kanban dowa vs kanban customer (kanban customer bisa di cocokkan dengan kanban dowa tanpa kanban internal)',
            'benefit' => 'Before 3 step scan kanban (DOWA, INTERNAL, CUSTOMER)
Ater 2 step scan kanban (DOWA, CUSTOMER)
Area FG torimetron dapat di eliminasi
Eliminate muda transfer ke area FG torimetron
Eliminate muda transfer ke area palletizing',
            'status' => 'IT Approve',
            'created_at' => '2026-06-18 08:26:15',
            'updated_at' => '2026-07-09 08:23:03',
        ],
        [
            'no_reg' => 'FTR/2606/006',
            'organization_user_id' => 376,
            'fullname' => 'Mokhamad Lukman Hakim',
            'system' => 'PREMAN',
            'title' => 'PREMAN ELECTRIC',
            'problem' => 'Penambahan check sheet preventive ASMP01 & ASIP02',
            'desired_outcome' => 'Total ada 11 mesin yang harus diupload ke PREMAN',
            'benefit' => 'Preventive terplaning & terschedule',
            'status' => 'IT Approve',
            'created_at' => '2026-06-17 10:51:01',
            'updated_at' => '2026-06-25 22:51:40',
        ],
        [
            'no_reg' => 'FTR/2606/005',
            'organization_user_id' => 420,
            'fullname' => 'Wahyu Setya Putra',
            'system' => 'PREMAN',
            'title' => 'Green sheet, Scalling schedule, Form improvement, Order repair Mold, SS form mte',
            'problem' => 'Tidak ada fitur tersebut',
            'desired_outcome' => 'Ada fitur yang di inginkan',
            'benefit' => 'Pekerjaan terkontrol dan problem tertangani dengan baik',
            'status' => 'IT Approve',
            'created_at' => '2026-06-06 08:19:37',
            'updated_at' => '2026-06-25 22:51:43',
        ],
        [
            'no_reg' => 'FTR/2607/003',
            'organization_user_id' => 299,
            'fullname' => 'Aditiya Teddy Marsha',
            'system' => 'https://henkaten.aiia.co.id',
            'title' => '/employee/skill',
            'problem' => 'menu tidak praktis saat perlu update',
            'desired_outcome' => 'menu menjadi praktis saat ada update',
            'benefit' => 'memudahkan update henkaten',
            'status' => 'Manager Approve',
            'created_at' => '2026-07-21 11:16:20',
            'updated_at' => '2026-07-28 12:51:49',
        ],
        [
            'no_reg' => 'FTR/2607/002',
            'organization_user_id' => 299,
            'fullname' => 'Aditiya Teddy Marsha',
            'system' => 'https://henkaten.aiia.co.id',
            'title' => '/employee/planning',
            'problem' => 'tidak ada menu edit',
            'desired_outcome' => 'ada menu edit',
            'benefit' => 'mempercepat setting planning harian',
            'status' => 'Manager Approve',
            'created_at' => '2026-07-20 11:29:25',
            'updated_at' => '2026-07-28 12:52:09',
        ],
        [
            'no_reg' => 'FTR/2607/005',
            'organization_user_id' => 1202,
            'fullname' => 'M. Fajri Ardha',
            'system' => 'Digital henkaten',
            'title' => 'Planning MP',
            'problem' => 'Tidak ada menu change line dan change shift',
            'desired_outcome' => 'Ditambahkan menu change line dan change shift ATAU disediakan template seperti template input quota pada sikola (tinggal upload)',
            'benefit' => 'Proses input planning shift MP lebih ringkas, tidak perlu hapus planning dan input ulang planning baru (Memangkas waktu)',
            'status' => 'Manager Approve',
            'created_at' => '2026-07-27 11:37:05',
            'updated_at' => '2026-07-28 12:52:22',
        ],
        [
            'no_reg' => 'FTR/2607/004',
            'organization_user_id' => 702,
            'fullname' => 'Widiyan',
            'system' => 'Tracebility Electric',
            'title' => 'Filter dan data base',
            'problem' => 'Tidak ada fitur filter dan data base hasil dari scan di produksi sebelumnya tidak ada',
            'desired_outcome' => 'Bisa melihat data base part yang sudah di proses dari awal produksi (2023)',
            'benefit' => 'Lebih mudah akses part yang ingin di cek',
            'status' => 'IT Approve',
            'created_at' => '2026-07-22 11:37:13',
            'updated_at' => '2026-07-31 13:56:51',
        ],
        [
            'no_reg' => 'FTR/2608/002',
            'organization_user_id' => 250,
            'fullname' => 'Armendo Rachmawan',
            'system' => 'BELLA',
            'title' => 'penambahan aktual line saat kanban di scan',
            'problem' => 'tidak diketahui kanban discan di line apa',
            'desired_outcome' => 'mudah diketahui kanban di scan di line apa',
            'benefit' => 'traceability lebih akurat',
            'status' => 'Manager Approve',
            'created_at' => '2026-08-07 15:04:48',
            'updated_at' => '2026-08-07 15:04:48',
        ],
        [
            'no_reg' => 'FTR/2608/001',
            'organization_user_id' => 349,
            'fullname' => 'Rizal Fahlepi',
            'system' => 'Bella',
            'title' => 'Seri kanban customer',
            'problem' => 'Seri kanban customer belum ada',
            'desired_outcome' => 'tersedia seri kanban customer',
            'benefit' => 'treace ability lebih akurat kanban customer vs kanban proses.',
            'status' => 'Manager Approve',
            'created_at' => '2026-08-07 15:03:42',
            'updated_at' => '2026-08-07 15:56:39',
        ],
        [
            'no_reg' => 'FTR/2608/003',
            'organization_user_id' => 2840,
            'fullname' => 'Gabriel Enzo Kovanda',
            'system' => 'Tripillar',
            'title' => 'Digitalisasi Patrol 3 Pillar',
            'problem' => 'Report Score 3 Pillar harus dilakukan rekap setiap bulan, kondisi aktual tidak terlihat',
            'desired_outcome' => 'Update score 3 Pillar bisa dilakukan secara realtime & memudahkan komite dan produksi dalam rekap temuan patrol',
            'benefit' => 'Tidak ada jam tambahan untuk update score secara manual, mengurangi penggunaan Kertas',
            'status' => 'Manager Approve',
            'created_at' => '2026-08-11 08:54:31',
            'updated_at' => '2026-08-11 10:35:57',
        ],
    ];

    public function run(): void
    {
        $directory = app(OrganizationDirectory::class);
        // The configured delivery workspace when this database has it, otherwise the first
        // workspace there is: a seeder must not die because one env value names a row that
        // only exists on another machine.
        $publicId = config('organization.workspace_public_id');
        $workspace = ($publicId ? Workspace::where('public_id', $publicId)->first() : null)
            ?? Workspace::query()->oldest('id')->first();

        if (! $workspace) {
            $this->command?->warn('No workspace in this database yet — run the workspace setup first.');

            return;
        }

        if ($publicId && $workspace->public_id !== $publicId) {
            $this->command?->warn('ORG_WORKSPACE_PUBLIC_ID ('.$publicId.') is not in this database; using '.$workspace->name.' instead.');
        }

        $imported = 0;
        $repaired = 0;
        $skipped = [];

        foreach (static::rows() as $row) {
            $system = $this->system($workspace, $row['system']);

            if (! $system) {
                $skipped[] = $row['no_reg'].' (system "'.$row['system'].'" not in the master list)';

                continue;
            }

            $requester = $this->requester($row);

            if (! $requester) {
                $skipped[] = $row['no_reg'].' (requester '.$row['organization_user_id'].' not in the directory)';

                continue;
            }

            $profile = $directory->profile($requester);

            // Title plus system plus requester is the closest thing to a key here: no_reg has
            // no column of its own, and re-running must not duplicate what it already imported.
            $request = FeatureRequest::firstOrNew([
                'workspace_id' => $workspace->id,
                'project_id' => $system->id,
                'requester_id' => $requester->id,
                'title' => Str::limit($row['title'], 200, ''),
            ]);

            if ($request->exists) {
                if ($this->needsItReviewRepair($request, $row)) {
                    $request->update(['status' => FeatureRequestStatus::PENDING_REVIEW]);
                    $repaired++;
                }

                continue;
            }

            $request->fill([
                'problem' => $row['problem'] ?? '—',
                'desired_outcome' => $row['desired_outcome'] ?? '—',
                'benefit' => $row['benefit'],
                'urgency' => RequestUrgency::NORMAL,
                'status' => static::STATUSES[strtolower((string) $row['status'])] ?? FeatureRequestStatus::PENDING_REVIEW,
                ...($profile ? $directory->snapshot($profile) : []),
            ]);

            // The dates belong to the old form, so the queue keeps its real waiting time.
            $request->created_at = $row['created_at'];
            $request->updated_at = $row['updated_at'] ?? $row['created_at'];
            $request->save();

            $imported++;
        }

        $this->command?->info($imported.' legacy feature requests imported and '.$repaired.' returned to IT review in '.$workspace->name.'.');

        foreach ($skipped as $note) {
            $this->command?->warn('Skipped '.$note);
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected static function rows(): array
    {
        return static::REQUESTS;
    }

    /** Only repairs untouched rows imported by the older mapping; real Orbitra decisions stay put. */
    private function needsItReviewRepair(FeatureRequest $request, array $row): bool
    {
        return strcasecmp((string) ($row['status'] ?? ''), 'IT Approve') === 0
            && $request->status === FeatureRequestStatus::APPROVED
            && $request->version === 1
            && $request->reviewed_at === null
            && $request->estimated_minutes === null
            && $request->assignee_id === null
            && $request->scheduled_start === null
            && $request->breakdowns()->doesntExist();
    }

    private function system(Workspace $workspace, ?string $application): ?Project
    {
        $name = static::SYSTEMS[strtolower(trim((string) $application))] ?? null;

        return $name
            ? $workspace->projects()->systems()->where('name', $name)->first()
            : null;
    }

    /**
     * The local account for whoever raised the request. Everyone in this dump is an AIIA
     * employee, so a missing account is one that has simply never signed in here yet.
     */
    private function requester(array $row): ?User
    {
        $person = DB::connection(config('organization.connection'))
            ->table('users')
            ->where('id', $row['organization_user_id'])
            ->first(['id', 'name', 'email']);

        if (! $person) {
            return null;
        }

        $user = User::where('organization_user_id', $person->id)->first()
            ?? User::whereRaw('LOWER(email) = ?', [strtolower($person->email)])->first();

        if ($user) {
            return $user;
        }

        [$first, $last] = $this->splitName($person->name ?: $row['fullname']);

        return User::create([
            'first_name' => $first,
            'last_name' => $last,
            'email' => $person->email,
            'email_verified_at' => now(),
            'auth_source' => 'organization',
            'organization_user_id' => $person->id,
            'organization_synced_at' => now(),
            // Sign-in goes through the directory, so this password is never the one checked.
            'password' => Str::random(64),
        ]);
    }

    /** @return array{string, string} */
    private function splitName(string $name): array
    {
        $parts = explode(' ', preg_replace('/\s+/', ' ', trim($name)), 2);

        return [Str::limit($parts[0] ?: 'User', 80, ''), Str::limit($parts[1] ?? '', 80, '')];
    }
}
