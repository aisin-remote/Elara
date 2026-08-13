<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

/**
 * The systems already running in the company, imported from the portal application list.
 * Name only: PIC, department, plant, and colour are filled in from the master data screen.
 */
class SystemMasterSeeder extends Seeder
{
    private const SYSTEMS = [
        'A-FAST',
        'ASIIC',
        'ASSA',
        'AVICENNA',
        'BELLA',
        'BROADCAST',
        'CEKDIRI',
        'CINTA',
        'CO2',
        'CSMS',
        'CUBIC PRO',
        'DEA',
        'DELIVERY',
        'DEVITA',
        'DIGITAL HENKATEN',
        'FIOLA',
        'GARY',
        'HR SYSTEM',
        'IATF',
        'MADONNA',
        'MAPS - BODY',
        'MAPS - UNIT',
        'OCA',
        'OEE',
        'PASYA',
        'PREMAN',
        'PRISMA',
        'PRODUCTION REPORT',
        'QIRA',
        'RECRUITMENT',
        'RISNA',
        'RTS - UNIT',
        'SATRIA',
        'SIGITA',
        'SIKOLA',
        'SISCA',
        'SOLID',
        'TRACEBILITY - ELECTRIC',
        'TRIPILAR',
        'WELCOME BOARD',
    ];

    public function run(): void
    {
        $workspace = Workspace::query()->oldest('id')->first();

        if (! $workspace) {
            $this->command?->warn('No workspace yet, skipping systems.');

            return;
        }

        foreach (self::SYSTEMS as $name) {
            $system = Project::firstOrNew([
                'workspace_id' => $workspace->id,
                'name' => $name,
                'type' => ProjectType::SYSTEM->value,
            ]);

            if ($system->exists) {
                continue;
            }

            $system->fill([
                'owner_id' => $workspace->owner_id,
                'status' => ProjectStatus::ACTIVE,
            ])->save();

            // Without these the system's board has no columns to drop a task into.
            TaskStatus::createDefaultsFor($system);
        }

        $this->command?->info(count(self::SYSTEMS).' systems checked in '.$workspace->name.'.');
    }
}
