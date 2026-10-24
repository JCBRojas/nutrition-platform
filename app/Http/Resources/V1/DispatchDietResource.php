<?php

namespace App\Http\Resources\V1;

use App\Models\Diet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dieta en el tablero del despachador (API v1). Solo los campos que usa la app: nunca el
 * modelo con sus relaciones de usuario (SCRUM-18). Usa los atributos que agrega
 * App\Services\DispatchBoard::diets() y 'delivery_status' (DispatchService::statusByDiet).
 *
 * @mixin Diet
 */
class DispatchDietResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $v = $this->currentVersion;
        $record = $this->dispatch_record ?? null;
        $missing = $this->dispatch_missing ?? null;

        return [
            'id' => $this->id,
            'habitation' => $this->habitation,
            'name_patient' => $this->name_patient,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'version' => $v?->version,
            'time_food' => $v?->timeFood ?? [],
            'consistency' => $v?->consistency ?? [],
            'specifications' => $v?->specifications ?? [],
            'observations' => $v?->observations,
            'isolation' => $v?->isolation,
            'billing_type' => $v?->billing_type,
            'has_pending' => (bool) $this->has_pending,
            'pending_changes' => $this->pending_changes ?? [],
            'last_change' => $this->last_change ?? null,
            // Alta con envío a otro lugar hoy: solo esos tiempos
            'transfer_meals' => $this->transferMealsOn(),
            'transfer_destination' => $this->transferMealsOn() ? $this->transfer_destination : null,
            // Estado en el servicio elegido (pestaña activas)
            'authorized_for_day' => $this->authorized_for_day ?? null,
            'block_reason' => $this->dispatch_block ?? null,
            'dispatch' => $record ? [
                'id' => $record->id,
                'dispatched_at' => $record->dispatched_at?->toIso8601String(),
                'dispatched_by' => $record->dispatchedBy?->name,
                'received_at' => $record->received_at?->toIso8601String(),
            ] : null,
            'missing' => $missing ? [
                'reason' => $missing->reason,
                'voided_at' => $missing->voided_at?->toIso8601String(),
            ] : null,
            // Tiempos del día: 'received' | 'dispatched'
            'delivery' => $this->delivery_status ?? (object) [],
        ];
    }
}
