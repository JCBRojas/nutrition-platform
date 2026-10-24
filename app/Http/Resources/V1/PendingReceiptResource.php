<?php

namespace App\Http\Resources\V1;

use App\Models\DietDispatch;
use App\Services\DispatchService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Despacho que espera la confirmación de recepción del nutricionista (API v1).
 *
 * @mixin DietDispatch
 */
class PendingReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'diet_id' => $this->diet_id,
            'habitation' => $this->diet?->habitation,
            'name_patient' => $this->diet?->name_patient,
            'meal' => $this->meal,
            'meal_label' => DispatchService::meals()[$this->meal] ?? $this->meal,
            'service_date' => $this->service_date?->toDateString(),
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'dispatched_by' => $this->dispatchedBy?->name,
        ];
    }
}
