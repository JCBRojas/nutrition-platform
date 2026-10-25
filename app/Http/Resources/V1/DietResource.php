<?php

namespace App\Http\Resources\V1;

use App\Models\Diet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dieta en el panel del nutricionista (API v1): los campos de Diet::editPayload() más el
 * estado del día. Del usuario que editó, solo el nombre (SCRUM-18). Usa 'delivery_status'
 * (DispatchService::statusByDiet) y 'day_status' (DietDayService::statusByDiet) si se agregaron.
 *
 * @mixin Diet
 */
class DietResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $v = $this->currentVersion;

        return [
            'id' => $this->id,
            'habitation' => $this->habitation,
            'name_patient' => $this->name_patient,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'version' => $v?->version,
            ...$this->editPayload()['current_version'],
            'updated_at' => ($v?->created_at ?? $this->updated_at)?->toIso8601String(),
            'updated_by' => $v?->updatedBy?->name,
            'transfer' => $this->transfer_destination ? [
                'destination' => $this->transfer_destination,
                'meals' => $this->transfer_meals ?? [],
                'date' => $this->transfer_date?->toDateString(),
            ] : null,
            'delivery' => $this->delivery_status ?? (object) [],
            'day' => $this->day_status ?? null,
        ];
    }
}
