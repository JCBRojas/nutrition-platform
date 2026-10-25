<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDietRequest;
use App\Http\Resources\V1\DietResource;
use App\Models\Diet;
use App\Services\DietDayService;
use App\Services\DietService;
use App\Services\DispatchService;
use App\Services\NutritionBoard;
use App\Support\CurrentCompany;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dietas del panel del nutricionista en la API v1: mismas reglas que la web (StoreDietRequest,
 * DietService, DietDayService). Las dietas se buscan con findAccessible dentro de cada acción
 * (403 si son de otra empresa), después de que UseCompanyHeader fija la empresa.
 */
class DietController extends Controller
{
    public function __construct(private DietService $diets) {}

    /** GET /diets?tab=active|cancelled|finished&q=&page=&per_page= */
    public function index(Request $request, NutritionBoard $board, DispatchService $dispatches, DietDayService $days): JsonResponse
    {
        $data = $request->validate([
            'tab' => ['nullable', Rule::in(array_keys(NutritionBoard::TABS))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $tab = $data['tab'] ?? 'active';

        $diets = $board->diets($tab, $data['q'] ?? '', Pagination::perPage($request->query('per_page')));
        $page = $diets->getCollection();
        if ($tab === 'active') {
            $ids = $page->pluck('id')->all();
            $delivery = $dispatches->statusByDiet($ids);
            $dayStatus = $days->statusByDiet($ids);
            $page->each(function (Diet $d) use ($delivery, $dayStatus) {
                $d->delivery_status = $delivery[$d->id] ?? (object) [];
                $d->day_status = $dayStatus[$d->id] ?? null;
            });
        }

        return response()->json([
            'tab' => $tab,
            'data' => DietResource::collection($page),
            'meta' => [
                'current_page' => $diets->currentPage(),
                'last_page' => $diets->lastPage(),
                'per_page' => $diets->perPage(),
                'total' => $diets->total(),
            ],
            'counts' => $board->counts(),
            'stats' => $board->stats(),
            // Aviso de habitación con dieta activa al registrar (SCRUM-17)
            'active_rooms' => Diet::active()->distinct()->pluck('habitation'),
        ]);
    }

    /** POST /diets: registrar (createDiets; reglas de StoreDietRequest) */
    public function store(StoreDietRequest $request, CurrentCompany $company): JsonResponse
    {
        $diet = $this->diets->createDiet([...$request->validated(), 'company_id' => $company->require()], $request->user()->id);

        return response()->json(new DietResource($diet->fresh(['currentVersion.updatedBy'])), 201);
    }

    /** PUT /diets/{diet}: nueva versión (editDiets; la habitación y el nombre no cambian) */
    public function update(StoreDietRequest $request, int $diet): JsonResponse
    {
        $model = Diet::findAccessible($diet);
        abort_unless($model->isActive(), 404, 'La dieta ya no está activa.');
        $this->diets->createNewVersion($model, $request->validated(), $request->user()->id);

        return response()->json(new DietResource($model->fresh(['currentVersion.updatedBy'])));
    }

    /** POST /diets/{diet}/continue: «Continuar dieta», autoriza el despacho de mañana */
    public function continue(Request $request, int $diet, DietDayService $days): JsonResponse
    {
        abort_unless($request->user()->can('continueDiets'), 403);
        $model = Diet::findAccessible($diet);
        $days->continueDiet($model, $request->user()->id);

        return response()->json(['message' => "Dieta de la habitación {$model->habitation} autorizada para mañana."]);
    }

    /**
     * GET /diets/{diet}/closing-summary: lo que conviene saber antes de cancelar o dar de alta
     * (despachos sin recibir, recibido hoy y tiempos que aún se pueden enviar a otro lugar).
     */
    public function closingSummary(int $diet, DispatchService $dispatches): JsonResponse
    {
        $model = $this->activeDiet($diet);

        return response()->json($dispatches->closingSummary($model));
    }

    /** POST /diets/{diet}/cancel */
    public function cancel(Request $request, int $diet): JsonResponse
    {
        $model = $this->activeDiet($diet);
        $this->diets->cancelDiet($model, $request->user()->id);

        return response()->json(['message' => "Dieta de la habitación {$model->habitation} cancelada."]);
    }

    /** POST /diets/{diet}/finish {transfer?: {destination, meals[]}}: alta, con envío opcional a otro lugar */
    public function finish(Request $request, int $diet): JsonResponse
    {
        $model = $this->activeDiet($diet);
        $data = $request->validate([
            'transfer' => ['nullable', 'array'],
            'transfer.destination' => ['required_with:transfer', 'string', 'max:150'],
            'transfer.meals' => ['required_with:transfer', 'array', 'min:1'],
            'transfer.meals.*' => ['string'],
        ], [
            'transfer.destination.required_with' => 'Indica a dónde se debe enviar la comida.',
            'transfer.meals.required_with' => 'Elige al menos un tiempo de comida para enviar.',
        ]);

        $this->diets->finishDiet($model, $request->user()->id, $data['transfer'] ?? null);

        return response()->json(['message' => "Dieta de la habitación {$model->habitation} finalizada por alta."]);
    }

    /** Dieta activa de la empresa (cancelar y dar de alta solo aplican a activas) */
    private function activeDiet(int $id): Diet
    {
        $diet = Diet::findAccessible($id);
        abort_unless($diet->isActive(), 404, 'La dieta ya no está activa.');

        return $diet->loadMissing('currentVersion');
    }
}
