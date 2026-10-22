<?php

namespace App\Livewire;

use App\Models\DietDispatch;
use App\Models\DietDispatchVoid;
use App\Services\DispatchService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Panel del nutricionista: despachos de cocina que esperan confirmación de recepción.
 * Al recibirse todos los tiempos del día, la dieta queda Completada; solo esos días se facturan.
 * Si un despacho no llegó, se reporta («No llegó») y cocina puede volver a despacharlo.
 */
class DispatchReceipts extends Component
{
    /** IDs de despachos marcados para confirmar */
    public array $selected = [];

    /** IDs ya mostrados; para avisar solo por los nuevos */
    public array $known = [];

    /** Despacho que se va a reportar como no recibido (modal abierto) y su motivo */
    public ?int $voidingId = null;

    public string $voidReason = '';

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

        $result = $dispatches->confirmReceipt(array_map('intval', $this->selected), auth()->id());
        $this->selected = [];
        $this->pendingCache = null;
        $this->known = $this->pending()->pluck('id')->all();

        $message = $result['received']
            ? "Recepción confirmada: {$result['received']} despacho(s)."
            : 'No había despachos pendientes en la selección.';
        if ($result['completed'] !== []) {
            $message .= ' Día completado: hab. '.implode(', ', $result['completed']).'.';
        }

        $this->dispatch('dispatch-received', message: $message);
    }

    /* ===================== NO LLEGÓ ===================== */

    public function startNotArrived(int $dispatchId): void
    {
        abort_unless(auth()->user()?->can('confirmReception'), 403);

        $this->voidingId = $dispatchId;
        $this->voidReason = '';
        $this->resetValidation();
    }

    public function cancelNotArrived(): void
    {
        $this->reset('voidingId', 'voidReason');
        $this->resetValidation();
    }

    public function confirmNotArrived(DispatchService $dispatches): void
    {
        abort_unless(auth()->user()?->can('confirmReception'), 403);
        $this->validate(['voidReason' => ['nullable', 'string', 'max:255']]);

        try {
            $void = $dispatches->voidDispatch((int) $this->voidingId, DietDispatchVoid::NOT_ARRIVED, $this->voidReason, auth()->id());
        } catch (ValidationException $e) {
            // Se recibió o se anuló mientras el modal estaba abierto
            $this->cancelNotArrived();
            $this->dispatch('dispatch-received', message: collect($e->errors())->flatten()->first());

            return;
        }
        $this->cancelNotArrived();
        $this->pendingCache = null;
        $this->known = $this->pending()->pluck('id')->all();

        $meal = mb_strtolower(DispatchService::meals()[$void->meal] ?? $void->meal);
        $this->dispatch('dispatch-received', message: "Reportado: no llegó {$meal} de la hab. {$void->diet?->habitation}. Cocina puede volver a despacharlo.");
    }

    public function render()
    {
        $pending = $this->pending();
        $ids = $pending->pluck('id')->all();
        // Lo que ya se confirmó (por otro nutricionista) se desmarca solo
        $this->selected = array_values(array_intersect(array_map('intval', $this->selected), $ids));
        // Si el despacho ya no está pendiente (se recibió o se anuló en otra pestaña), se cierra el modal
        $voiding = $this->voidingId ? $pending->firstWhere('id', $this->voidingId) : null;
        if ($this->voidingId && ! $voiding) {
            $this->reset('voidingId', 'voidReason');
        }

        return view('livewire.dispatch-receipts', [
            'pending' => $pending,
            'meals' => DispatchService::meals(),
            'allSelected' => $ids !== [] && count($this->selected) === count($ids),
            'voiding' => $voiding,
        ]);
    }

    private function pending(): Collection
    {
        return $this->pendingCache ??= app(DispatchService::class)->pendingReceipts();
    }
}
