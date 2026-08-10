<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\Planning\WeeklyInsightsGenerator;
use Illuminate\Console\Command;

class GenerateWeeklyInsights extends Command
{
    protected $signature = 'orbitra:generate-weekly-insights';

    protected $description = 'Generate weekly delivery insights for every workspace';

    public function handle(WeeklyInsightsGenerator $generator): int
    {
        Workspace::query()->orderBy('id')->each(function (Workspace $workspace) use ($generator): void {
            $insight = $generator->generate($workspace);
            $this->line($workspace->name.': '.$insight->public_id.' ('.$insight->source.')');
        });

        return self::SUCCESS;
    }
}
