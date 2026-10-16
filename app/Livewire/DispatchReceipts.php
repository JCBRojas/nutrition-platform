<?php

namespace App\Livewire;

use App\Models\DietDispatch;
use App\Services\DispatchService;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Panel del nutricionista: despachos de cocina que esperan confirmación de recepción.
 * Solo los despachos recibidos se facturan (BillingService).
 */
class DispatchReceipts extends Component
{
    /** IDs de despachos marcados para confirmar */
    public array $selected = [];

    /** IDs ya mostrados; para avisar solo por los nuevos */
    public array $known = [];

    private ?Collection $pendingCache = null;

    public function mount(): void
    {
        $this->known = $this->pending()->pluck('id')->all();
    }

    /** Llamado por wire:poll: avisa si llegaron despachos nuevos */
    public function checkNew(): void
    {
        $ids = $this->pending()->pluck('id')->all();
        $new = array_diff($ids, $this->known);
        $this->known = $ids;

        if ($new !== []) {
            $rooms = $this->pending()->whereIn('id', $new)->map(fn (DietDispatch $d) => $d->diet?->habitation)->unique()->values()->all();
            $this->dispatch('dispatch-arrived', count: count($new), rooms: $rooms);
        }
    }

    public function toggleAll(): void
    {
        $ids = $this->pending()->pluck('id')->all();
        $this->selected = array_diff($ids, array_map('intval', $this->selected)) === [] ? [] : $ids;
    }

    public function confirmReceipt(DispatchService $dispatches): void
    {
        abort_unless(auth()->user()?->can('confirmReception'), 403);

        $count = $dispatches->confirmReceipt(array_map('intval', $this->selected), auth()->id());
        $this->selected = [];
        $this->pendingCache = null;
        $this->known = $this->pending()->pluck('id')->all();

        $this->dispatch('dispatch-received', message: $count
            ? "Recepción confirmada: {$count} despacho(s)."
            : 'No había despachos pendientes en la selección.');
    }

    public function render()
    {
        $pending = $this->pending();
        $ids = $pending->pluck('id')->all();
        // Lo que ya se confirmó (por otro nutricionista) se desmarca solo
        $this->selected = array_values(array_intersect(array_map('intval', $this->selected), $ids));

        return view('livewire.dispatch-receipts', [
            'pending' => $pending,
            'meals' => DispatchService::meals(),
            'allSelected' => $ids !== [] && count($this->selected) === count($ids),
        ]);
    }

    private function pending(): Collection
    {
        return $this->pendingCache ??= app(DispatchService::class)->pendingReceipts();
    }
}
