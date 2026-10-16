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
        $schedule->command('backup:run')->dailyAt('01:00');
        $schedule->command('backup:clean')->dailyAt('01:30');
        $schedule->command('backup:monitor')->dailyAt('02:00');
        $schedule->command('backups:verify')->weeklyOn(1, '03:00'); // lunes: prueba de restauración
        $schedule->command('diets:start-day')->dailyAt('00:01');
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
