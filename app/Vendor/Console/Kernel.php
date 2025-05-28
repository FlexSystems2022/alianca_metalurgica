<?php

namespace App\Vendor\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('ExecutaTudoCadastros')->everyMinute()->withoutOverlapping();
        $schedule->command('ExecutaTudoAusencias')->everyThirtyMinutes()->withoutOverlapping();
        $schedule->command('ExecutaTudoTrocaPostos')->everyThirtyMinutes()->withoutOverlapping();
        $schedule->command('ExecutaTudoTrocaEscalas')->everyThirtyMinutes()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(app_path('Console/Commands'));

        require base_path('routes/console.php');
    }
}
