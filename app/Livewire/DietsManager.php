<?php

namespace App\Livewire;

use App\Models\Diet;
use App\Models\DietHistory;
use App\Services\DietService;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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

    /** Claves "dieta:versión" pendientes que ya se mostraron; para alertar solo por las nuevas */
    public array $knownPending = [];

    /** Total de dietas con cambios sin confirmar (título de la pestaña del navegador) */
    public int $pendingCount = 0;

    private ?Collection $pendingCache = null;

    public function mount(): void
    {
        $this->tab = in_array($this->tab, ['active', 'closed'], true) ? $this->tab : 'active';
        $this->perPage = Pagination::perPage($this->perPage);
        $this->knownPending = $this->pendingKeys();
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

        $diet = Diet::findOrFail($dietId);
        $diets->confirmChanges($diet);

        $this->pendingCache = null;
        $this->knownPending = $this->pendingKeys();
        $this->dispatch('diet-change-confirmed', room: $diet->habitation);
    }

    /* ===================== RENDER ===================== */

    public function render()
    {
        $statuses = $this->tab === 'closed' ? [Diet::CANCELLED, Diet::FINISHED] : [Diet::ACTIVE];

        $diets = Diet::query()
            ->whereIn('status', $statuses)
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('habitation', 'like', "%{$this->search}%")
                ->orWhere('name_patient', 'like', "%{$this->search}%")))
            ->withExists(['history as has_pending' => fn (Builder $q) => $q->where('modify', 'si')])
            ->with(['currentVersion.updatedBy', 'history'])
            ->orderByDesc('has_pending') // los cambios sin confirmar siempre arriba
            ->when($this->tab === 'closed', fn (Builder $q) => $q->latest('updated_at'), fn (Builder $q) => $q->orderBy('habitation'))
            ->paginate($this->perPage);

        $diets->getCollection()->each(function (Diet $diet) {
            $diet->pending_changes = $diet->has_pending ? $this->changesSinceConfirmed($diet) : [];
        });

        $pending = $this->pending();
        $this->pendingCount = $pending->count();

        return view('livewire.diet-manager', [
            'diets' => $diets,
            'pending' => $pending,
            'counts' => [
                'active' => Diet::active()->count(),
                'closed' => Diet::whereIn('status', [Diet::CANCELLED, Diet::FINISHED])->count(),
                'pendingActive' => $pending->where('status', Diet::ACTIVE)->count(),
                'pendingClosed' => $pending->where('status', '!=', Diet::ACTIVE)->count(),
            ],
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
