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
        $schedule->command('orbitra:send-mom-reminders')->dailyAt('08:00')->timezone('Asia/Jakarta')->withoutOverlapping();
        $schedule->command('orbitra:drain-request-queue')->hourly()->withoutOverlapping();
        // After the drain: a takedown in this sweep frees capacity the next drain absorbs.
        $schedule->command('orbitra:sweep-validations')->hourly()->withoutOverlapping();
        $schedule->command('orbitra:generate-weekly-insights')->weeklyOn(1, '08:00')->withoutOverlapping();
        $schedule->command('orbitra:sync-holidays')
            ->monthlyOn(1, '02:00')
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping();

        // Drains the queue from the same cron entry that runs this scheduler, so a deployment
        // needs one crontab line and no second process manager.
        //
        // --stop-when-empty is what makes it safe to start every minute: the worker exits once
        // the queue is clear instead of living forever and being spawned again next minute.
        // withoutOverlapping() covers the case where a batch outlasts the minute, and
        // runInBackground() keeps a slow batch from delaying the hourly commands above.
        //
        // Cost of this over a resident worker: a job waits up to 60 seconds before it starts.
        // Nothing here is interactive — breakdowns, notifications, and sweeps all tolerate it.
        // Swap in a systemd worker if that ever stops being true, and delete this line.
        $schedule->command('queue:work --stop-when-empty --tries=3 --backoff=10 --max-time=55')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();
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
