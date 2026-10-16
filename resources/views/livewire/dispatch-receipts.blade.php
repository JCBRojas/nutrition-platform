@php $total = $pending->count(); @endphp
<div wire:poll.5s.keep-alive="checkNew"
    x-data="{ toast: null }"
    x-on:dispatch-arrived.window="toast = 'Cocina despachó a hab. ' + $event.detail.rooms.join(', ') + '. Confirma la recepción.'"
    x-on:dispatch-received.window="toast = $event.detail.message">

    @if ($total > 0)
        <section class="dr-panel mb-4" role="region" aria-labelledby="dr-title">
            <div class="dr-head">
                <h2 id="dr-title" class="dr-title">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 3h15v13H1zM16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    {{ $total === 1 ? '1 despacho' : "{$total} despachos" }} por confirmar recepción
                </h2>
                <p class="dr-help">Solo los despachos con recepción confirmada se cuentan en la facturación.</p>
            </div>

            <div class="table-responsive">
                <table class="table-wrap dr-table">
                    <thead>
                        <tr>
                            <th class="dr-check">
                                <input type="checkbox" class="dr-checkbox" aria-label="Seleccionar todos los despachos"
                                    @checked($allSelected) wire:click="toggleAll">
                            </th>
                            <th>Hab.</th>
                            <th>Paciente</th>
                            <th>Servicio</th>
                            <th>Fecha</th>
                            <th>Despachó</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pending as $d)
                            <tr wire:key="dispatch-{{ $d->id }}" @class(['dr-row-selected' => in_array($d->id, $selected)])>
                                <td class="dr-check">
                                    <input type="checkbox" class="dr-checkbox" value="{{ $d->id }}" wire:model.live="selected"
                                        aria-label="Confirmar recepción: habitación {{ $d->diet?->habitation }}, {{ $meals[$d->meal] ?? $d->meal }}">
                                </td>
                                <td><span class="room-badge">{{ $d->diet?->habitation }}</span></td>
                                <td>{{ $d->diet?->name_patient }}</td>
                                <td><span class="tag tag-tiempo">{{ $meals[$d->meal] ?? $d->meal }}</span></td>
                                <td>
                                    {{ $d->service_date->locale('es')->isoFormat('D MMM') }}
                                    <div class="dr-meta">{{ $d->dispatched_at->format('g:i a') }} · v{{ $d->version?->version }}</div>
                                </td>
                                <td>{{ $d->dispatchedBy?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @can('confirmReception')
                <div class="dr-actions">
                    <button type="button" class="btn btn-sm dr-confirm" wire:click="confirmReceipt"
                        @disabled(count($selected) === 0)
                        wire:confirm="¿Confirmas que se recibieron {{ count($selected) }} despacho(s)? Se contarán en la facturación."
                        wire:loading.attr="disabled" wire:target="confirmReceipt">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                        Confirmar recepción ({{ count($selected) }})
                    </button>
                </div>
            @endcan
        </section>
    @endif

    <div class="dr-toast" role="status" aria-live="polite" x-show="toast" x-cloak x-transition
        x-effect="if (toast) { clearTimeout(window.drToastTimer); window.drToastTimer = setTimeout(() => toast = null, 5000) }"
        x-text="toast"></div>

    <style>
        .dr-panel { border: 2px solid #bfdbfe; border-left: 8px solid var(--blue); background: #eff6ff; border-radius: 10px; padding: 14px 16px; }
        .dr-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px 16px; flex-wrap: wrap; margin-bottom: 10px; }
        .dr-title { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 15px; font-weight: 700; color: #1e3a8a; }
        .dr-help { margin: 0; font-size: 12.5px; color: var(--text-muted); }
        .dr-table { background: #fff; border-radius: 8px; }
        .dr-check { width: 40px; }
        .dr-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: var(--blue); }
        .dr-checkbox:focus-visible, .dr-confirm:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .dr-row-selected td { background: #dbeafe; }
        .dr-meta { font-size: 11.5px; color: var(--text-muted); }
        .dr-actions { display: flex; justify-content: flex-end; margin-top: 10px; }
        .dr-confirm { background: var(--blue); color: #fff; font-weight: 700; border: 0; }
        .dr-confirm[disabled] { opacity: .5; cursor: not-allowed; }
        .dr-toast { position: fixed; right: 20px; bottom: 20px; z-index: 300; max-width: min(360px, calc(100vw - 32px)); background: var(--text); color: #fff; padding: 12px 16px; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.2); font-size: 13.5px; }
        @media (max-width: 640px) { .dr-actions .dr-confirm { width: 100%; justify-content: center; } }
    </style>
</div>
