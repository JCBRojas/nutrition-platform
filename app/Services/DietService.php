<?php

namespace App\Services;

use App\Models\Diet;
use App\Models\DietDay;
use App\Models\DietHistory;
use App\Support\LiveUpdates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DietService
{
    /**
     * Crear dieta con versión inicial
     */
    public function createDiet(array $data, int $userId): Diet
    {
        // dd($data['timeFood']);
        return DB::transaction(function () use ($data, $userId) {

            // 1️⃣ Crear cabecera
            $diet = Diet::create([
                // Empresa activa de quien la crea (NUT-RN-003-07); no cambia en versiones posteriores
                'company_id' => $data['company_id'] ?? null,
                'habitation' => $data['habitation'],
                'name_patient' => $data['name_patient'],
                'status' => Diet::ACTIVE,
            ]);

            // 2️⃣ Crear versión 1
            $this->createInternalVersionOfDiet($diet, $data, $userId, 1, $modify = 'no');

            // 3️⃣ Crearla autoriza el despacho del día; los siguientes requieren «Continuar dieta»
            DietDay::create([
                'diet_id' => $diet->id,
                'service_date' => now()->toDateString(),
                'authorized_via' => DietDay::VIA_CREATION,
                'authorized_by' => $userId,
                'authorized_at' => now(),
            ]);

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

            if (($data['changes'] ?? null) === 'cancelada') {
                $this->setDietCanceled($diet);
            }

            return $this->createInternalVersionOfDiet(
                $diet,
                $data,
                $userId,
                $lastVersion + 1,
                $modify = 'si'
            );

        });
    }

    /**
     * Método privado reutilizable
     */
    private function createInternalVersionOfDiet(Diet $diet, array $data, int $userId, int $numeroVersion, string $modify): DietHistory
    {

        return DietHistory::create([
            'diet_id' => $diet->id,
            'updated_by' => $userId,
            'version' => $numeroVersion,
            'timeFood' => $data['timeFood'] ?? null,
            'consistency' => $data['consistency'] ?? null,
            'specifications' => $data['specifications'] ?? null,
            // Tipo de facturación deducido de consistencia y especificaciones (ya no se elige)
            'billing_type' => BillingService::typeFor($data['consistency'] ?? null, $data['specifications'] ?? null) ?? ($data['billing_type'] ?? null),
            'observations' => $data['observations'] ?? null,
            'isolation' => $data['isolation'] ?? 'No',
            'changes' => $data['changes'] ?? null,
            'modify' => $modify ?? 'no',
        ]);
    }

    /**
     * Cancelar dieta: crea una nueva versión con changes = 'cancelada'
     * (copia la versión actual) y desactiva la cabecera.
     */
    public function cancelDiet(Diet $diet, int $userId): DietHistory
    {
        return $this->closeDiet($diet, $userId, 'cancelada', Diet::CANCELLED);
    }

    /**
     * Finalizar dieta (alta del paciente): crea una versión con changes = 'finalizada'
     * (copia de la actual) y pone status = FINISHED. No elimina registros.
     */
    public function finishDiet(Diet $diet, int $userId, ?array $transfer = null): DietHistory
    {
        $history = $this->closeDiet($diet, $userId, 'finalizada', Diet::FINISHED, $this->transferAttributes($diet, $transfer));

        // Si ya se recibió todo lo servido antes del alta, el día queda completado (y se factura)
        app(DietDayService::class)->evaluate($diet->fresh(), now(), $userId);

        return $history;
    }

    /**
     * Envío a otro lugar en el alta (p. ej. remitido a otra IPS): destino y tiempos de hoy
     * que cocina aún no despacha. null o sin destino = no se envía nada.
     *
     * @param  array{destination?: ?string, meals?: array<int, string>}|null  $transfer
     */
    private function transferAttributes(Diet $diet, ?array $transfer): array
    {
        $destination = trim((string) ($transfer['destination'] ?? ''));
        if ($destination === '') {
            return [];
        }

        $meals = array_values(array_unique((array) ($transfer['meals'] ?? [])));
        $allowed = array_keys(app(DispatchService::class)->transferCandidates($diet));

        if (mb_strlen($destination) > 150) {
            throw ValidationException::withMessages(['transfer.destination' => 'Escribe el destino con 150 caracteres como máximo.']);
        }
        if ($meals === [] || array_diff($meals, $allowed) !== []) {
            throw ValidationException::withMessages(['transfer.meals' => 'Elige los tiempos de hoy que se deben enviar al destino (solo los que cocina aún no despacha).']);
        }

        return [
            'transfer_destination' => $destination,
            'transfer_meals' => array_values(array_intersect($allowed, $meals)), // orden cronológico
            'transfer_date' => now()->toDateString(),
        ];
    }

    private function closeDiet(Diet $diet, int $userId, string $changes, int $status, array $attributes = []): DietHistory
    {
        if (! $diet->isActive()) {
            throw ValidationException::withMessages([
                'diet' => "La dieta de la habitación {$diet->habitation} ya está {$diet->statusLabel()} y no se puede modificar.",
            ]);
        }

        $current = $diet->currentVersion;

        return DB::transaction(function () use ($diet, $userId, $changes, $status, $current, $attributes) {
            $history = $this->createNewVersion($diet, [
                'timeFood' => $current?->timeFood,
                'consistency' => $current?->consistency,
                'specifications' => $current?->specifications,
                'billing_type' => $current?->billing_type,
                'observations' => $current?->observations,
                'isolation' => $current?->isolation ?? 'No',
                'changes' => $changes,
            ], $userId);

            $diet->update(['status' => $status] + $attributes);

            return $history;
        });
    }

    /**
     * Versión que registra el sistema para el día (Diet::DAY_CHANGES: completada, continuada):
     * copia de la actual, sin cambio clínico. No alerta al despachador; si ya había cambios
     * sin confirmar, queda pendiente con ellos para que la comparación siga mostrándolos.
     */
    public function recordDayVersion(Diet $diet, string $changes, int $userId): DietHistory
    {
        return DB::transaction(function () use ($diet, $changes, $userId) {
            $lastVersion = $diet->history()->lockForUpdate()->max('version') ?? 0;
            $current = $diet->history()->where('version', $lastVersion)->first();
            $pending = $diet->history()->where('modify', 'si')->exists();

            return $this->createInternalVersionOfDiet($diet, [
                'timeFood' => $current?->timeFood,
                'consistency' => $current?->consistency,
                'specifications' => $current?->specifications,
                'billing_type' => $current?->billing_type,
                'observations' => $current?->observations,
                'isolation' => $current?->isolation ?? 'No',
                'changes' => $changes,
            ], $userId, $lastVersion + 1, $pending ? 'si' : 'no');
        });
    }

    /**
     * El despachador confirma que vio los cambios de la dieta antes de entregarla:
     * marca todas sus versiones pendientes (modify = 'si') como confirmadas.
     * Es el único campo de DietHistory que se modifica.
     */
    public function confirmChanges(Diet $diet): int
    {
        $count = $diet->history()->where('modify', 'si')->update(['modify' => 'no']);
        LiveUpdates::touch(LiveUpdates::DIET, $diet->company_id);

        return $count;
    }

    private function setDietCanceled(Diet $diet): bool
    {
        return $diet->update([
            'status' => Diet::CANCELLED,
        ]);
    }
}
