<?php

namespace App\Services;

use App\Models\Diet;
use App\Models\DietHistory;
use Illuminate\Support\Facades\DB;

class DietService
{
    /**
     * Crear dieta con versión inicial
     */
    public function createDiet(array $data, int $userId): Diet
    {
        return DB::transaction(function () use ($data, $userId) {

            // 1️⃣ Crear cabecera
            $diet = Diet::create([
                'habitation' => $data['habitation'],
                'name_patient' => $data['name_patient'],
                'status' => true, // Activa por defecto
            ]);

            // 2️⃣ Crear versión 1
            $this->createInternalVersionOfDiet($diet, $data, $userId, 1);

            return $diet;
        });
    }

    /**
     * Crear nueva versión
     */
    public function createNewVersion(Diet $diet, array $data, int $userId): DietHistory
    {
        return DB::transaction(function () use ($diet, $data, $userId) {

            $lastVersion = $diet->history()
                                   ->lockForUpdate()
                                   ->max('version') ?? 0;

            if($data['changes'] === 'cancelada') {
                $this->setDietCanceled($diet);
            }

            return $this->createInternalVersionOfDiet(
                $diet,
                $data,
                $userId,
                $lastVersion + 1
            );
            
        });
    }

    /**
     * Método privado reutilizable
     */
    private function createInternalVersionOfDiet( Diet $diet, array $data, int $userId, int $numeroVersion): DietHistory {

        return DietHistory::create([
            'diet_id' => $diet->id,
            'updated_by' => $userId,
            'version' => $numeroVersion,
            'timeFood' => $data['timeFood'] ?? null,
            'consistency' => $data['consistency'] ?? null,
            'specifications' => $data['specifications'] ?? null,
            'observations' => $data['observations'] ?? null,
            'isolation' => $data['isolation'] ?? 'No',
            'changes' => $data['changes'] ?? null,
        ]);
    }

    private function setDietCanceled(Diet $diet): bool
    {
        return $diet->update([
            'status' => false,
        ]);
    }
}