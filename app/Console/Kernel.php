<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('orbitra:send-deadline-reminders')->hourly()->withoutOverlapping();
        $schedule->command('orbitra:drain-request-queue')->hourly()->withoutOverlapping();
        // After the drain: a takedown in this sweep frees capacity the next drain absorbs.
        $schedule->command('orbitra:sweep-validations')->hourly()->withoutOverlapping();
        $schedule->command('orbitra:generate-weekly-insights')->weeklyOn(1, '08:00')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
