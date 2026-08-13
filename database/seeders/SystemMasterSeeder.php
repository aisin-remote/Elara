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

        $taken = Project::systems()->pluck('color')->filter()->map('strtolower')->all();
        $hue = 0;

        foreach (self::SYSTEMS as $name) {
            $system = Project::firstOrNew([
                'workspace_id' => $workspace->id,
                'name' => $name,
                'type' => ProjectType::SYSTEM->value,
            ]);

            if ($system->exists && $system->color) {
                continue;
            }

            do {
                $color = $this->hueColor($hue++);
            } while (in_array($color, $taken, true));
            $taken[] = $color;

            $fresh = ! $system->exists;

            $system->fill([
                'owner_id' => $workspace->owner_id,
                'status' => ProjectStatus::ACTIVE,
                'color' => $color,
            ])->save();

            // Without these the system's board has no columns to drop a task into.
            if ($fresh) {
                TaskStatus::createDefaultsFor($system);
            }
        }

        $this->command?->info(count(self::SYSTEMS).' systems checked in '.$workspace->name.'.');
    }

    /**
     * Golden-angle hues at a fixed saturation and lightness: consecutive systems land far
     * apart on the wheel, so the dots stay tellable without hand-picking 40 colours.
     */
    private function hueColor(int $index): string
    {
        $sector = fmod($index * 137.508, 360) / 60;
        $chroma = 0.5;
        $second = $chroma * (1 - abs(fmod($sector, 2) - 1));
        $lift = 0.58 - $chroma / 2;

        [$r, $g, $b] = match ((int) $sector) {
            0 => [$chroma, $second, 0.0],
            1 => [$second, $chroma, 0.0],
            2 => [0.0, $chroma, $second],
            3 => [0.0, $second, $chroma],
            4 => [$second, 0.0, $chroma],
            default => [$chroma, 0.0, $second],
        };

        return sprintf(
            '#%02x%02x%02x',
            (int) round(($r + $lift) * 255),
            (int) round(($g + $lift) * 255),
            (int) round(($b + $lift) * 255),
        );
    }
}
