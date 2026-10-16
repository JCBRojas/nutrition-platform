<?php

namespace App\Support;

use App\Events\DietsChanged;
use Illuminate\Support\Facades\DB;

/**
 * Junta los cambios de una petición y emite un solo DietsChanged al confirmar la transacción
 * (o de inmediato si no hay transacción). Si el servidor de WebSockets no responde, el error
 * se registra y la operación del usuario sigue: las pantallas se ponen al día con el respaldo.
 */
class LiveUpdates
{
    public const DIET = 'diet';

    public const DISPATCH = 'dispatch';

    public const DAY = 'day';

    /** @var array<string, true> */
    private static array $pending = [];

    private static bool $muted = false;

    public static function touch(string $kind): void
    {
        if (self::$muted) {
            return;
        }

        self::$pending[$kind] = true;

        // Un callback por cambio: el primero que corre emite todo lo acumulado y los demás no hacen nada.
        // Si la transacción se revierte, lo acumulado sale con el siguiente aviso (solo provoca un refresco).
        DB::afterCommit(fn () => self::flush());
    }

    public static function flush(): void
    {
        $kinds = array_keys(self::$pending);
        self::$pending = [];

        if ($kinds !== []) {
            rescue(fn () => event(new DietsChanged($kinds)));
        }
    }

    /** Ejecuta sin avisar (seeders y cargas masivas) */
    public static function muted(callable $callback): mixed
    {
        $previous = self::$muted;
        self::$muted = true;

        try {
            return $callback();
        } finally {
            self::$muted = $previous;
        }
    }
}
