<?php

namespace App\Livewire;

use App\Models\Diet;
use App\Models\DietDispatch;
use App\Models\DietDispatchVoid;
use App\Services\DietService;
use App\Services\DispatchBoard;
use App\Services\DispatchService;
use App\Support\Pagination;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Vista del despachador (cocina). Las versiones con modify = 'si' son cambios que el
 * nutricionista hizo y el despachador aún no confirma: se listan primero, se resaltan
 * y generan una alerta (sonido + notificación del navegador) cuando llegan por el poll.
 */
class DietsManager extends Component
{
    use WithPagination;

    #[Url(except: 'active')]
    public string $tab = 'active'; // active | closed

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'per_page', except: Pagination::PER_PAGE)]
    public int $perPage = Pagination::PER_PAGE;

    /** Tiempo de comida que se está despachando (clave de config/timeFood.php) */
    #[Url(as: 'servicio')]
    public string $meal = '';

    /** true (por defecto) = todas las activas; false = solo las que incluyen el servicio elegido (?todas=0) */
    #[Url(as: 'todas', except: true)]
    public bool $showAll = true;

    /** IDs de dietas marcadas como listas para despachar */
    public array $selected = [];

    /** Claves "dieta:versión" pendientes que ya se mostraron; para alertar solo por las nuevas */
    public array $knownPending = [];

    /** Total de dietas con cambios sin confirmar (título de la pestaña del navegador) */
    public int $pendingCount = 0;

    /** Despacho que se va a anular (modal abierto) y su motivo */
    public ?int $voidingId = null;

    public string $voidReason = '';

    private ?Collection $pendingCache = null;

    public function mount(): void
    {
        $this->tab = in_array($this->tab, ['active', 'closed'], true) ? $this->tab : 'active';
        $this->perPage = Pagination::perPage($this->perPage);
        $this->meal = array_key_exists($this->meal, DispatchService::meals()) ? $this->meal : DispatchService::currentMeal();
        $this->knownPending = $this->pendingKeys();
    }

    public function updatedMeal(): void
    {
        $this->meal = array_key_exists($this->meal, DispatchService::meals()) ? $this->meal : DispatchService::currentMeal();
        $this->selected = [];
        $this->resetPage();
    }

    public function updatedShowAll(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = Pagination::perPage($this->perPage);
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'closed' ? 'closed' : 'active';
        $this->selected = [];
        $this->resetPage();
    }

    /* ===================== ALERTAS ===================== */

    /**
     * Llamado por wire:poll: avisa al navegador si llegaron cambios que no se habían visto
     */
    public function checkPending(): void
    {
        $keys = $this->pendingKeys();
        $new = array_values(array_diff($keys, $this->knownPending));
        $this->knownPending = $keys;

        if ($new === []) {
            return;
        }

        $rooms = $this->pending()
            ->filter(fn (Diet $d) => in_array(DispatchBoard::pendingKey($d), $new, true))
            ->map(fn (Diet $d) => $d->habitation)
            ->values()
            ->all();

        $this->dispatch('diet-changes-arrived', count: count($new), rooms: $rooms);
    }

    /* ===================== CONFIRMAR ===================== */

    public function confirmChanges(int $dietId, DietService $diets): void
    {
        abort_unless(auth()->user()?->can('confirmDelivery'), 403);

        $diet = Diet::findAccessible($dietId);
        $diets->confirmChanges($diet);

        $this->pendingCache = null;
        $this->knownPending = $this->pendingKeys();
        $this->dispatch('diet-change-confirmed', room: $diet->habitation);
    }

    /* ===================== DESPACHAR ===================== */

    /** Marca o desmarca todas las dietas despachables de la página visible */
    public function togglePage(array $ids): void
    {
        $ids = array_map('intval', $ids);
        $this->selected = array_map('intval', $this->selected);
        $this->selected = array_diff($ids, $this->selected) === []
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique([...$this->selected, ...$ids]));
    }

    public function dispatchSelected(DispatchService $dispatches): void
    {
        abort_unless(auth()->user()?->can('confirmDelivery'), 403);

        if ($this->selected === []) {
            $this->dispatch('diet-dispatched', message: 'Selecciona al menos una dieta lista para despachar.');

            return;
        }

        $result = $dispatches->dispatch($this->selected, $this->meal, auth()->id());
        $this->selected = [];

        $meal = mb_strtolower(DispatchService::meals()[$this->meal]);
        $message = $result['dispatched']
            ? count($result['dispatched'])." dieta(s) despachada(s) ({$meal}). El nutricionista debe confirmar la recepción."
            : 'No se despachó ninguna dieta.';
        foreach ($result['skipped'] as $room => $reason) {
            $message .= " Hab. {$room}: {$reason}";
        }

        $this->dispatch('diet-dispatched', message: $message);
    }

    /* ===================== ANULAR DESPACHO ===================== */

    /** Cocina se equivocó: anula un despacho que aún no se recibe */
    public function startCancelDispatch(int $dispatchId): void
    {
        abort_unless(auth()->user()?->can('confirmDelivery'), 403);

        $this->voidingId = $dispatchId;
        $this->voidReason = '';
        $this->resetValidation();
    }

    public function closeCancelDispatch(): void
    {
        $this->reset('voidingId', 'voidReason');
        $this->resetValidation();
    }

    public function confirmCancelDispatch(DispatchService $dispatches): void
    {
        abort_unless(auth()->user()?->can('confirmDelivery'), 403);
        $this->validate(['voidReason' => ['nullable', 'string', 'max:255']]);

        try {
            $void = $dispatches->voidDispatch((int) $this->voidingId, DietDispatchVoid::CANCELLED, $this->voidReason, auth()->id());
        } catch (ValidationException $e) {
            // Se recibió o se anuló mientras el modal estaba abierto
            $this->closeCancelDispatch();
            $this->dispatch('diet-dispatched', message: collect($e->errors())->flatten()->first());

            return;
        }
        $this->closeCancelDispatch();

        $meal = mb_strtolower(DispatchService::meals()[$void->meal] ?? $void->meal);
        $this->dispatch('diet-dispatched', message: "Despacho anulado: {$meal} de la hab. {$void->diet?->habitation}. Puedes volver a despacharlo.");
    }

    /* ===================== RENDER ===================== */

    public function render()
    {
        $board = app(DispatchBoard::class);
        $filterByMeal = $this->tab === 'active' && ! $this->showAll;
        $diets = $board->diets($this->tab, $this->meal, $this->showAll, $this->search, $this->perPage);
        $dispatchService = app(DispatchService::class);
        $dispatchable = $this->tab === 'active' ? $board->dispatchable($diets->getCollection()) : [];
        // Lo que dejó de ser despachable (cambio nuevo, ya despachado) se desmarca solo
        $this->selected = array_values(array_filter(array_map('intval', $this->selected), fn ($id) => ! $diets->getCollection()->contains('id', $id) || in_array($id, $dispatchable, true)));

        $pending = $this->pending();
        $this->pendingCount = $pending->count();

        // Si el despacho ya no está sin recibir (se recibió o se anuló en otra pestaña), se cierra el modal
        $voiding = $this->voidingId
            ? DietDispatch::pendingReceipt()->with(['diet', 'dispatchedBy'])->find($this->voidingId)
            : null;
        if ($this->voidingId && ! $voiding) {
            $this->reset('voidingId', 'voidReason');
        }

        return view('livewire.diet-manager', [
            'diets' => $diets,
            'pending' => $pending,
            'meals' => DispatchService::meals(),
            'delivery' => $this->tab === 'active' ? $dispatchService->statusByDiet($diets->getCollection()->pluck('id')->all()) : [],
            'dispatchable' => $dispatchable,
            'voiding' => $voiding,
            'counts' => $board->counts($pending, $this->meal, ! $filterByMeal),
            'foodLabels' => config('timeFood.labels'),
            'consistencyLabels' => config('consistency.labels'),
            'specLabels' => config('specifications.labels'),
            'changeLabels' => config('changes.labels'),
        ]);
    }

    /* ===================== INTERNOS ===================== */

    /** Dietas con versiones sin confirmar, la más reciente primero */
    private function pending(): Collection
    {
        return $this->pendingCache ??= app(DispatchBoard::class)->pending();
    }

    private function pendingKeys(): array
    {
        return $this->pending()->map(fn (Diet $d) => DispatchBoard::pendingKey($d))->all();
    }
}
