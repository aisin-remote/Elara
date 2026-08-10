<?php

namespace App\Console\Commands;

use App\Actions\Request\ScheduleApprovedRequests;
use App\Models\Workspace;
use Illuminate\Console\Command;

class DrainRequestQueue extends Command
{
    protected $signature = 'orbitra:drain-request-queue';

    protected $description = 'Give approved requests the earliest slot real capacity allows';

    public function handle(ScheduleApprovedRequests $scheduler): int
    {
        $total = 0;

        // Capacity frees up constantly: a task finishes early, leave is cancelled, a request
        // is taken down. Re-running the planner hourly is what turns that into a schedule.
        Workspace::query()->chunkById(50, function ($workspaces) use ($scheduler, &$total): void {
            foreach ($workspaces as $workspace) {
                $total += $scheduler->handle($workspace);
            }
        });

        $this->info("Scheduled {$total} request(s).");

        return self::SUCCESS;
    }
}
