<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DietDispatch;
use App\Services\DispatchService;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «Despachos del día» en la API v1 (Premium dispatch_log, permiso viewDispatches), como
 * App\Livewire\DispatchLog: lo despachado y recibido por fecha y servicio, el resumen por
 * tiempo y los anulados o que no llegaron.
 */
class DispatchLogController extends Controller
{
    /** GET /dispatches/day?fecha=YYYY-MM-DD&servicio=&estado=pending|received&q= */
    public function __invoke(Request $request, DispatchService $dispatches): JsonResponse
    {
        $data = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'servicio' => ['nullable', Rule::in(array_keys(DispatchService::meals()))],
            'estado' => ['nullable', Rule::in(['pending', 'received'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $date = CarbonImmutable::parse($data['fecha'] ?? now()->toDateString());
        $meal = $data['servicio'] ?? '';
        $status = $data['estado'] ?? '';
        $search = trim($data['q'] ?? '');

        $rows = $dispatches->dayQuery($date, $meal, $status, $search)
            ->with(['diet', 'dispatchedBy:id,name', 'receivedBy:id,name'])
            ->orderByRoom()
            ->paginate(Pagination::perPage($request->query('per_page')));

        return response()->json([
            'date' => $date->toDateString(),
            'data' => $rows->getCollection()->map(fn (DietDispatch $d) => [
                'id' => $d->id,
                'diet_id' => $d->diet_id,
                'habitation' => $d->diet?->habitation,
                'name_patient' => $d->diet?->name_patient,
                'meal' => $d->meal,
                'dispatched_at' => $d->dispatched_at?->toIso8601String(),
                'dispatched_by' => $d->dispatchedBy?->name,
                'received_at' => $d->received_at?->toIso8601String(),
                'received_by' => $d->receivedBy?->name,
            ])->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'summary' => $dispatches->daySummary($date),
            // Sin estado de recepción: solo cuando no se filtra por estado (como la web)
            'voids' => $status === '' ? $dispatches->dayVoids($date, $meal, $search)->map(fn ($v) => [
                'id' => $v->id,
                'habitation' => $v->diet?->habitation,
                'meal' => $v->meal,
                'kind' => $v->kind,
                'label' => $v->label(),
                'reason' => $v->reason,
                'voided_at' => $v->voided_at?->toIso8601String(),
                'voided_by' => $v->voidedBy?->name,
            ])->values() : [],
        ]);
    }
}
