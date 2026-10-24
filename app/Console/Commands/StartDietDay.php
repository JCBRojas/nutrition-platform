<?php

namespace App\Console\Commands;

use App\Services\DietDayService;
use Illuminate\Console\Command;

/**
 * Inicio del día: autoriza las dietas abiertas que nadie continuó (autorización automática)
 * y las Completadas autorizadas para hoy vuelven a Activa.
 */
class StartDietDay extends Command
{
    protected $signature = 'diets:start-day';

    protected $description = 'Autoriza el día de las dietas abiertas y vuelve a Activa las completadas';

    public function handle(DietDayService $days): int
    {
        $result = $days->startDay();
        $this->info("{$result['authorized']} dieta(s) autorizada(s) automáticamente; {$result['reactivated']} volvieron a Activa.");

        return self::SUCCESS;
    }
}
