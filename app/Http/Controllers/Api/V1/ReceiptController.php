<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PendingReceiptResource;
use App\Services\DispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recepción del nutricionista en la API v1 (como App\Livewire\DispatchReceipts): despachos
 * por confirmar y confirmación; al recibirse todos los tiempos del día, la dieta queda Completada.
 */
class ReceiptController extends Controller
{
    public function __construct(private DispatchService $dispatches) {}

    public function pending(): JsonResponse
    {
        return response()->json(['data' => PendingReceiptResource::collection($this->dispatches->pendingReceipts())]);
    }

    /** POST /receipts {dispatch_ids: int[]} */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dispatch_ids' => ['required', 'array', 'min:1'],
            'dispatch_ids.*' => ['integer'],
        ]);

        return response()->json($this->dispatches->confirmReceipt(array_map('intval', $data['dispatch_ids']), $request->user()->id));
    }
}
