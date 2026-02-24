<?php

namespace App\Livewire;

use App\Models\Paciente;
use Livewire\Component;

class PacienteForm extends Component
{
    public $habitacion, $nombre, $tiempo_comida, $consistencia, $especificaciones, $observaciones, $aislamiento, $cambios;

    public $showModal = false;

public function abrirModal()
{
    $this->showModal = true;
}

public function cerrarModal()
{
    $this->showModal = false;
}
    public function guardar()
    {
        $paciente = Paciente::create([
            'habitacion' => $this->habitacion,
            'nombre' => $this->nombre,
            'tiempo_comida' => $this->tiempo_comida,
            'consistencia' => $this->consistencia,
            'especificaciones' => $this->especificaciones,
            'observaciones' => $this->observaciones,
            'aislamiento' => $this->aislamiento,
            'cambios' => $this->cambios,
        ]);

        $this->reset(); // limpia los campos
        $this->emit('pacienteActualizado', $paciente); // emite evento para otros componentes
    }

    public function render()
    {
        return view('livewire.paciente-form');
    }

}
