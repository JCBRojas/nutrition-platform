<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Aviso en tiempo real de que cambiaron dietas, despachos o el ciclo del día. Reemplaza el
 * polling: las pantallas abiertas se refrescan solo al recibirlo. No lleva datos clínicos
 * (solo los tipos de cambio); cada pantalla vuelve a consultar lo que le corresponde.
 */
class DietsChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public const CHANNEL = 'diets';

    /** @param  array<int, string>  $kinds  diet | dispatch | day */
    public function __construct(public array $kinds) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::CHANNEL)];
    }

    public function broadcastAs(): string
    {
        return 'diets.changed';
    }

    public function broadcastWith(): array
    {
        return ['kinds' => $this->kinds];
    }
}
