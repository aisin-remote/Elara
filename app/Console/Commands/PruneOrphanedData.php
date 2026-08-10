<?php

namespace App\Console\Commands;

use App\Services\OrphanedDataPruner;
use Illuminate\Console\Command;

class PruneOrphanedData extends Command
{
    protected $signature = 'orbitra:prune-orphaned-data {--force : Delete orphaned data without confirmation}';

    protected $description = 'Delete orphaned records and clear nullable references to missing parent records';

    public function handle(OrphanedDataPruner $pruner): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete every orphaned database record now?')) {
            return self::FAILURE;
        }

        $changes = $pruner->prune();

        if ($changes === []) {
            $this->info('No orphaned data found.');

            return self::SUCCESS;
        }

        $this->table(['Operation', 'Rows'], collect($changes)->map(fn (int $rows, string $operation) => [
            $operation,
            $rows,
        ])->values()->all());
        $this->info('Orphaned data removed.');

        return self::SUCCESS;
    }
}
