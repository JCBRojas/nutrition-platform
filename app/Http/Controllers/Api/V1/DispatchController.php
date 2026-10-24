<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DispatchDietResource;
use App\Models\Diet;
use App\Models\DietDispatchVoid;
use App\Services\DietService;
use App\Services\DispatchBoard;
use App\Services\DispatchService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Despacho de cocina en la API v1: el mismo tablero, reglas y servicios que la pantalla web
 * (App\Livewire\DietsManager → App\Services\DispatchBoard / DispatchService).
 */
class DispatchController extends Controller
{
    public function __construct(private DispatchBoard $board, private DispatchService $dispatches) {}

    /** GET /dispatch/board?servicio=&tab=active|closed&todas=1&q=&page=&per_page= */
    public function board(Request $request): JsonResponse
    {
        $data = $request->validate([
            'servicio' => ['nullable', Rule::in(array_keys(DispatchService::meals()))],
            'tab' => ['nullable', Rule::in(['active', 'closed'])],
            'todas' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $meal = $data['servicio'] ?? DispatchService::currentMeal();
        $tab = $data['tab'] ?? 'active';
        $showAll = $tab !== 'active' || $request->boolean('todas', true);

        $diets = $this->board->diets($tab, $meal, $showAll, trim($data['q'] ?? ''), Pagination::perPage($request->query('per_page')));
        $page = $diets->getCollection();
        $page->loadMissing('history');
        if ($tab === 'active') {
            $delivery = $this->dispatches->statusByDiet($page->pluck('id')->all());
            $page->each(function (Diet $d) use ($delivery) {
                $d->delivery_status = $delivery[$d->id] ?? (object) [];
                $d->dispatch_record?->loadMissing('dispatchedBy:id,name');
            });
        }
        $pending = $this->board->pending();

        return response()->json([
            'meal' => $meal,
            'tab' => $tab,
            'show_all' => $showAll,
            'data' => DispatchDietResource::collection($page),
            'dispatchable' => $tab === 'active' ? $this->board->dispatchable($page) : [],
            'meta' => [
                'current_page' => $diets->currentPage(),
                'last_page' => $diets->lastPage(),
                'per_page' => $diets->perPage(),
                'total' => $diets->total(),
            ],
            // Cambios sin confirmar (para la alerta y el contador): una clave por versión nueva
            'pending' => $pending->map(fn (Diet $d) => [
                'diet_id' => $d->id,
                'key' => DispatchBoard::pendingKey($d),
                'habitation' => $d->habitation,
                'name_patient' => $d->name_patient,
                'last_change' => $d->last_change,
                'is_active' => $d->isActive() || (bool) $d->transferMealsOn(),
            ])->values(),
            'counts' => $this->board->counts($pending, $meal, $showAll),
        ]);
    }

    /** POST /dispatch {diet_ids: int[], meal} */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'diet_ids' => ['required', 'array', 'min:1'],
            'diet_ids.*' => ['integer'],
            'meal' => ['required', Rule::in(array_keys(DispatchService::meals()))],
        ]);

        $result = $this->dispatches->dispatch(array_map('intval', $data['diet_ids']), $data['meal'], $request->user()->id);

        return response()->json($result);
    }

    /**
     * POST /diets/{diet}/confirm-changes. En la API se busca con findAccessible (403 si es de otra
     * empresa) y no con route model binding, que se resuelve antes de que UseCompanyHeader fije
     * la empresa del encabezado.
     */
    public function confirmChanges(int $diet, DietService $diets): JsonResponse
    {
        $diet = Diet::findAccessible($diet);
        $diets->confirmChanges($diet);

        return response()->json(['message' => "Cambios de la hab. {$diet->habitation} confirmados."]);
    }

    /**
     * POST /dispatches/{dispatch}/void {kind: not_arrived|cancelled, reason?}
     * «No llegó» (nutrición, confirmReception) o «Anular despacho» (cocina, confirmDelivery).
     */
    public function void(Request $request, int $dispatch): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in([DietDispatchVoid::NOT_ARRIVED, DietDispatchVoid::CANCELLED])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $permission = $data['kind'] === DietDispatchVoid::NOT_ARRIVED ? 'confirmReception' : 'confirmDelivery';
        abort_unless($request->user()->can($permission), 403);

        $void = $this->dispatches->voidDispatch($dispatch, $data['kind'], $data['reason'] ?? null, $request->user()->id);

        return response()->json([
            'id' => $void->id,
            'diet_id' => $void->diet_id,
            'meal' => $void->meal,
            'kind' => $void->kind,
            'label' => $void->label(),
        ]);
    }
}
