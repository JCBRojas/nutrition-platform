{{--
    Confirmar que un despacho no llegó (nutricionista) o anularlo por error (despachador).
    $dispatch: DietDispatch con diet; $kind: DietDispatchVoid::NOT_ARRIVED | CANCELLED;
    $confirm / $cancel: métodos del componente Livewire; el motivo va en wire:model="voidReason".
--}}
@props(['dispatch', 'kind', 'meals', 'confirm', 'cancel'])
@php $notArrived = $kind === \App\Models\DietDispatchVoid::NOT_ARRIVED; @endphp
<div class="overlay open" wire:key="void-modal-{{ $dispatch->id }}" x-on:keydown.escape.window="$wire.{{ $cancel }}()"
    role="dialog" aria-modal="true" aria-labelledby="voidModalTitle">
    <div class="modal">
        <div class="modal-header">
            <h2 id="voidModalTitle">{{ $notArrived ? 'El despacho no llegó' : 'Anular despacho' }} · Hab. {{ $dispatch->diet?->habitation }}</h2>
            <button type="button" class="close-btn" wire:click="{{ $cancel }}" aria-label="Cerrar">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
            </button>
        </div>
        <form wire:submit="{{ $confirm }}">
            <div class="modal-body vdm-body">
                <p class="vdm-lead">
                    <strong>{{ $dispatch->diet?->name_patient }}</strong> ·
                    <span class="tag tag-tiempo">{{ $meals[$dispatch->meal] ?? $dispatch->meal }}</span>
                    despachado a las {{ $dispatch->dispatched_at->format('g:i a') }}
                    @unless ($dispatch->service_date->isToday())
                        del {{ $dispatch->service_date->locale('es')->isoFormat('D [de] MMMM') }}
                    @endunless
                    por {{ $dispatch->dispatchedBy?->name ?? '—' }}.
                </p>
                <p class="vdm-info">
                    {{ $notArrived
                        ? 'Queda como no recibido y no se factura. Cocina ve el aviso y puede volver a despacharlo.'
                        : 'Se anula y la dieta vuelve a quedar lista para despachar en este servicio. Nutrición ya no lo verá por recibir.' }}
                </p>
                <label class="vdm-field">
                    <span>Motivo (opcional)</span>
                    <input type="text" class="filter-input" wire:model="voidReason" maxlength="255"
                        placeholder="{{ $notArrived ? 'Ej.: no llegó a la habitación' : 'Ej.: se despachó la habitación equivocada' }}">
                </label>
                @error('voidReason') <p class="vdm-error" role="alert">{{ $message }}</p> @enderror
                @error('dispatch') <p class="vdm-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" wire:click="{{ $cancel }}">Volver</button>
                <button type="submit" class="btn btn-danger" wire:loading.attr="disabled" wire:target="{{ $confirm }}">
                    {{ $notArrived ? 'Reportar que no llegó' : 'Anular despacho' }}
                </button>
            </div>
        </form>
    </div>
    <style>
        .vdm-body { display: grid; gap: 12px; }
        .vdm-lead { margin: 0; }
        .vdm-info { margin: 0; padding: 10px 12px; border-radius: 8px; background: var(--color-primario-suave); color: var(--color-primario-suave-texto); font-size: 13px; }
        .vdm-field { display: grid; gap: 4px; font-size: 13px; font-weight: 600; }
        .vdm-error { margin: 0; color: var(--color-error); font-size: 12.5px; font-weight: 600; }
    </style>
</div>
