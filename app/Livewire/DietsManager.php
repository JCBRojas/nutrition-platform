<?php

namespace App\Livewire;

use App\Models\Diet;
use App\Models\DietDispatch;
use App\Models\DietDispatchVoid;
use App\Models\DietHistory;
use App\Services\DietDayService;
use App\Services\DietService;
use App\Services\DispatchService;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
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

    /** Campos que se comparan para mostrar qué cambió */
    private const COMPARED_FIELDS = ['timeFood', 'consistency', 'specifications', 'observations', 'isolation'];

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
            ->filter(fn (Diet $d) => in_array($this->pendingKey($d), $new, true))
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
        $statuses = $this->tab === 'closed' ? [Diet::CANCELLED, Diet::FINISHED] : Diet::OPEN_STATUSES;

        $filterByMeal = $this->tab === 'active' && ! $this->showAll;

        $diets = Diet::query()
            // Activas: también las dadas de alta hoy con comida por enviar a otro lugar
            ->where(fn (Builder $q) => $q->whereIn('status', $statuses)
                ->when($this->tab === 'active', fn (Builder $q) => $q->orWhere(fn (Builder $q) => $q->transferOn())))
            ->when($filterByMeal, fn (Builder $q) => $this->appliesToMeal($q))
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('habitation', 'like', "%{$this->search}%")
                ->orWhere('name_patient', 'like', "%{$this->search}%")))
            ->withExists(['history as has_pending' => fn (Builder $q) => $q->where('modify', 'si')])
            ->with(['currentVersion.updatedBy', 'history'])
            ->orderByDesc('has_pending') // los cambios sin confirmar siempre arriba; luego por habitación
            ->orderByRoom()
            ->latest('updated_at')
            ->paginate($this->perPage);

        $diets->getCollection()->each(function (Diet $diet) {
            $diet->pending_changes = $diet->has_pending ? $this->changesSinceConfirmed($diet) : [];
        });

        // Estado de despacho de cada dieta de la página en el tiempo elegido
        $dispatchService = app(DispatchService::class);
        $dispatches = $this->tab === 'active'
            ? $dispatchService->dispatchesFor($diets->getCollection()->pluck('id')->all(), $this->meal)
            : collect();
        $voids = $this->tab === 'active'
            ? $dispatchService->voidsFor($diets->getCollection()->pluck('id')->all(), $this->meal)
            : collect();
        $authorized = $this->tab === 'active'
            ? app(DietDayService::class)->authorizedIds($diets->getCollection()->pluck('id')->all())
            : [];
        $diets->getCollection()->each(function (Diet $diet) use ($dispatches, $voids, $dispatchService, $authorized) {
            $diet->authorized_for_day = in_array($diet->id, $authorized, true);
            $diet->dispatch_record = $dispatches->get($diet->id);
            // Nutrición reportó que no llegó: aviso para volver a despacharlo
            $void = $diet->dispatch_record ? null : $voids->get($diet->id);
            $diet->dispatch_missing = $void?->isNotArrived() ? $void : null;
            $diet->dispatch_block = $this->tab === 'active' ? $dispatchService->blockReason($diet, $this->meal) : null;
        });
        $dispatchable = $diets->getCollection()
            ->filter(fn (Diet $d) => ! $d->dispatch_record && ! $d->dispatch_block)
            ->pluck('id')
            ->all();
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
            'counts' => [
                'active' => Diet::active()->count(),
                'closed' => Diet::whereIn('status', [Diet::CANCELLED, Diet::FINISHED])->count(),
                'pendingActive' => $pending->filter(fn (Diet $d) => $d->isActive() || $d->transferMealsOn())->count(),
                'pendingClosed' => $pending->reject(fn (Diet $d) => $d->isActive() || $d->transferMealsOn())->count(),
                'transfers' => Diet::transferOn()->count(),
                'applies' => $filterByMeal ? $this->appliesToMeal(Diet::active())->count() : null,
            ],
            'foodLabels' => config('timeFood.labels'),
            'consistencyLabels' => config('consistency.labels'),
            'specLabels' => config('specifications.labels'),
            'changeLabels' => config('changes.labels'),
        ]);
    }

    /* ===================== INTERNOS ===================== */

    /**
     * Dietas cuya versión actual incluye el servicio elegido, más las que tienen cambios
     * sin confirmar (el despachador debe verlas aunque el cambio haya quitado ese tiempo).
     */
    private function appliesToMeal(Builder $query): Builder
    {
        // Claves guardadas de un mismo tiempo: 'mediamanana' / 'media_manana' (JSON: ["almuerzo","cena"])
        $keys = $this->meal === 'mediamanana' ? ['mediamanana', 'media_manana'] : [$this->meal];

        return $query->where(fn (Builder $q) => $q
            ->whereHas('currentVersion', fn (Builder $v) => $v->where(function (Builder $v) use ($keys) {
                foreach ($keys as $key) {
                    $v->orWhere('timeFood', 'like', '%"'.$key.'"%');
                }
            }))
            ->orWhereHas('history', fn (Builder $h) => $h->where('modify', 'si')));
    }

    /** Dietas con versiones sin confirmar, la más reciente primero */
    private function pending(): Collection
    {
        return $this->pendingCache ??= Diet::query()
            ->whereHas('history', fn (Builder $q) => $q->where('modify', 'si'))
            ->with('currentVersion.updatedBy')
            ->get()
            ->sortByDesc(fn (Diet $d) => $d->currentVersion?->created_at)
            ->values();
    }

    private function pendingKeys(): array
    {
        return $this->pending()->map(fn (Diet $d) => $this->pendingKey($d))->all();
    }

    private function pendingKey(Diet $diet): string
    {
        return $diet->id.':'.($diet->currentVersion?->version ?? 0);
    }

    /**
     * Diferencias entre la versión actual y la última que el despachador confirmó
     * (si el nutricionista editó varias veces, se ven todos los cambios acumulados).
     */
    private function changesSinceConfirmed(Diet $diet): array
    {
        $versions = $diet->history->sortBy('version')->values();
        $current = $versions->last();
        $baseline = $versions->last(fn (DietHistory $h) => $h->version < $current->version && $h->modify === 'no')
            ?? $versions->get($versions->count() - 2);

        if (! $current || ! $baseline) {
            return [];
        }

        $changes = [];
        foreach (self::COMPARED_FIELDS as $field) {
            $old = $baseline->$field;
            $new = $current->$field;

            if (is_array($old) || is_array($new)) {
                $old = array_values((array) $old);
                $new = array_values((array) $new);
                $added = array_values(array_diff($new, $old));
                $removed = array_values(array_diff($old, $new));
                if ($added || $removed) {
                    $changes[$field] = ['added' => $added, 'removed' => $removed];
                }
            } elseif ((string) $old !== (string) $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }
}
