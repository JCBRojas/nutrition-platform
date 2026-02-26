<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Paciente;

class PacientesManager extends Component
{
    /* ===================== FORM ===================== */
    public $habitation;
    public $namePatient;
    public $observations;
    public $isolation;

    /* ===================== INLINE EDIT ===================== */
    public $editId = null;
    public $editData = [];

    /* ===================== OPTIONS ===================== */
    public array $timeFood = [
        'Desayuno',
        'Almuerzo',
        'Cena',
        'Fraccionada',
    ];

    public array $consistency = [
        'Líquida Completa',
        'Líquida Clara',
        'Muy Blanda',
        'Blanda mecánica',
        'Supraglótica',
        'Normal',
    ];

    public array $specifications = [
        'Hipoglúcida',
        'Hiperprotéica',
        'Hiposódica',
        'Hipograsa',
        'Astringente',
        'Alta en fibra',
        'Sin lácteos',
        'Sin irritantes',
        'Hipercalórica',
    ];

    public array $changes = [
        'CANCELADA',
        'CON CAMBIOS',
        'SE AISLÓ',
        'YA NO ESTA AISLADO',
    ];


    public function cancelEdit()
    {
        $this->editId = null;
        $this->editData = [];
    }

    /* ===================== CONFIRM ===================== */
    public function confirmRecord($id)
    {
        $paciente = Paciente::find($id);

        if (! $paciente) {
            return;
        }

        $paciente->confirmed = ! (bool) $paciente->confirmed;
        $paciente->save();
    }

    /* ===================== RENDER ===================== */
    public function render()
    {
        return view('livewire.pacientes-manager', [
            'pacientes' => Paciente::latest()->get(),
            'timeFood' => $this->timeFood,
            'consistency' => $this->consistency,
            'specifications' => $this->specifications,
        ]);
    }
}