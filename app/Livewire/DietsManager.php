<?php

namespace App\Livewire;

use App\Models\Diet;
use App\Models\DietHistory;
use Livewire\Component;

class DietsManager extends Component
{

    public $confirmmedChanges = '';

    /* ===================== CONFIRM ===================== */

    public function confirmRecord($id)
    {
        $diet = Diet::find($id);

        if (!$diet) {
            return;
        }

        $diet->confirmed = ! (bool) $diet->confirmed;
        $diet->save();
    }


    /* ===================== DETECTAR CAMBIOS ===================== */

    private function detectChanges($current, $previous)
    {
        if (!$previous) {
            return [];
        }

        $changes = [];

        foreach ([
            'timeFood',
            'consistency',
            'specifications',
            'observations',
            'isolation'
        ] as $field) {

            $new = $current->$field ?? null;
            $old = $previous->$field ?? null;

            if ($new != $old) {

                $changes[$field] = [
                    'old' => (array) $old,
                    'new' => (array) $new
                ];
            }
        }
        $this->confirmmedChanges = $changes ? 'bg-yellow-50 border-l-4 border-yellow-400' : '';
        return $changes;
    }

    /* ===================== CONFIRMCHANGES ===================== */

    public function confirmChanges($id)
    {
        $diets = DietHistory::find($id);
        // dd($diets);
        $diets->modify = 'no';
        $diets->save();
        return $diets;
        // $this->dispatch('confirmChanges', 'id': $id);
    }

    /* ===================== RENDER ===================== */

    public function render()
    {

        $diets = Diet::with(['currentVersion', 'history'])->get();

        foreach ($diets as $diet) {

            $versions = $diet->history->sortByDesc('version')->values();

            $current = $versions->first();
            $previous = $versions->skip(1)->first();

            $diet->version_changes = $this->detectChanges($current, $previous);
        }

        return view('livewire.diet-manager', [
            'diets' => $diets
        ]);
    }
}