<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Diet;
use App\Services\AuditLogger;
use App\Services\DietHistoryService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «Historial de cambios» en la app de escritorio, como App\Livewire\DietHistoryViewer: dietas
 * de la empresa y la línea de tiempo de versiones de una (DietHistoryService). Solo los roles de
 * config('desktop.nutrition_roles') con viewDiets; en la app no exige Premium.
 */
class HistoryController extends Controller
{
    private const STATUSES = ['all', 'active', 'cancelled', 'finished'];

    /** GET /history/diets?q=&estado=all|active|cancelled|finished&page=&per_page= */
    public function diets(Request $request): JsonResponse
    {
        $this->authorizeHistory($request);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(self::STATUSES)],
        ]);
        $search = trim($data['q'] ?? '');
        $status = $data['estado'] ?? 'all';

        $diets = Diet::query()
            ->withCount('history')
            ->when($status === 'active', fn ($q) => $q->active())
            ->when($status === 'cancelled', fn ($q) => $q->where('status', Diet::CANCELLED))
            ->when($status === 'finished', fn ($q) => $q->where('status', Diet::FINISHED))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('habitation', 'like', "%{$search}%")
                ->orWhere('name_patient', 'like', "%{$search}%")))
            ->orderByRoom()
            ->orderByDesc('updated_at') // misma habitación: la dieta más reciente primero
            ->paginate(Pagination::perPage($request->query('per_page')));

        return response()->json([
            'data' => $diets->getCollection()->map(fn (Diet $d) => [
                'id' => $d->id,
                'habitation' => $d->habitation,
                'name_patient' => $d->name_patient,
                'status' => $d->status,
                'is_active' => $d->isActive(),
                'versions' => $d->history_count,
                'updated_at' => $d->updated_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $diets->currentPage(),
                'last_page' => $diets->lastPage(),
                'per_page' => $diets->perPage(),
                'total' => $diets->total(),
            ],
        ]);
    }

    /** GET /diets/{diet}/history: versiones de la más reciente a la más antigua, con su diferencia */
    public function show(Request $request, int $diet, DietHistoryService $history): JsonResponse
    {
        $this->authorizeHistory($request);
        $model = Diet::findAccessible($diet);
        // Ley 1581 / historia clínica: queda registro de quién consultó el historial (sin datos clínicos)
        AuditLogger::log('history_viewed', "Consultó el historial de la dieta de la hab. {$model->habitation}", $model, ['client' => 'desktop']);

        return response()->json([
            'diet' => [
                'id' => $model->id,
                'habitation' => $model->habitation,
                'name_patient' => $model->name_patient,
                'status' => $model->status,
                'is_active' => $model->isActive(),
            ],
            'versions' => $history->timeline($model)->map(fn (array $row) => [
                'version' => $row['version']->version,
                'type' => $row['type'],
                'created_at' => $row['version']->created_at?->toIso8601String(),
                // Del usuario que editó, solo el nombre (SCRUM-18)
                'updated_by' => $row['version']->updatedBy?->name,
                'fields' => $row['fields'],
            ])->values(),
        ]);
    }

    private function authorizeHistory(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(config('desktop.nutrition_roles')) && $user->can('viewDiets'), 403, 'El historial de cambios es solo para nutrición.');
    }
}
