<?php
namespace App\Events;

use App\Models\Paciente;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PacienteActualizado implements ShouldBroadcast
{
    public $paciente;

    public function __construct(Paciente $paciente)
    {
        $this->paciente = $paciente;
    }

    public function broadcastOn()
    {
        return new Channel('pacientes');
    }
}