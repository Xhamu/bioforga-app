<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Aquí registramos nuestro comando
        //$schedule->command('logs:eliminar-duplicados')->dailyAt('23:55');
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
    }
}
