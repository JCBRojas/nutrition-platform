<?php

namespace App\Console\Commands;

use App\Services\DietDayService;
use Illuminate\Console\Command;

/**
 * Inicio del día: las dietas Completadas que se continuaron para hoy vuelven a Activa.
 * Las que no se continuaron siguen Completadas y cocina no puede despacharlas.
 */
class StartDietDay extends Command
{
    protected $signature = 'diets:start-day';

    protected $description = 'Vuelve a Activa las dietas completadas que se continuaron para hoy';

    public function handle(DietDayService $days): int
    {
        $count = $days->startDay();
        $this->info("{$count} dieta(s) continuada(s) para hoy volvieron a Activa.");

        return self::SUCCESS;
    }
}
