@php $total = $pending->count(); @endphp
{{-- Tiempo real: se actualiza al llegar un aviso de despachos (resources/js/live.js); sin WebSocket, cada 5 s --}}
<div x-data="{ toast: null }"
    x-init="window.ntLive(() => $wire.checkNew(), { kinds: ['dispatch'], fallbackMs: 5000 })"
    x-on:dispatch-arrived.window="toast = 'Cocina despachó a hab. ' + $event.detail.rooms.join(', ') + '. Confirma la recepción.'"
    x-on:dispatch-received.window="toast = $event.detail.message">

    @if ($total > 0)
        {{-- Panel compacto: encabezado de una línea con la acción y lista con alto máximo (desplazable); se puede plegar --}}
        <section class="dr-panel mb-4" role="region" aria-labelledby="dr-title"
            x-data="{ open: (() => { try { return localStorage.getItem('dr-open') !== '0' } catch (e) { return true } })() }"
            x-effect="try { localStorage.setItem('dr-open', open ? '1' : '0') } catch (e) {}">
            <div class="dr-head">
                <button type="button" class="dr-toggle" x-on:click="open = ! open" :aria-expanded="open" aria-controls="dr-body">
                    <svg class="dr-chevron" :class="open && 'dr-chevron-open'" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24" aria-hidden="true"><polyline points="9 6 15 12 9 18"/></svg>
                    <h2 id="dr-title" class="dr-title">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 3h15v13H1zM16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        {{ $total === 1 ? '1 despacho' : "{$total} despachos" }} por confirmar recepción
                    </h2>
                </button>
                <span class="dr-help" title="Solo los despachos con recepción confirmada se cuentan en la facturación.">Solo lo recibido se factura</span>

                @can('confirmReception')
                    <button type="button" class="btn btn-sm dr-confirm" wire:click="confirmReceipt"
                        @disabled(count($selected) === 0)
                        wire:confirm="¿Confirmas que se recibieron {{ count($selected) }} despacho(s)? Se contarán en la facturación."
                        wire:loading.attr="disabled" wire:target="confirmReceipt">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                        Confirmar recepción ({{ count($selected) }})
                    </button>
                @endcan
            </div>

            <div id="dr-body" class="dr-body" x-show="open" x-cloak>
                <table class="dr-table">
                    <thead>
                        <tr>
                            <th class="dr-check">
                                <input type="checkbox" class="dr-checkbox" aria-label="Seleccionar todos los despachos"
                                    @checked($allSelected) wire:click="toggleAll">
                            </th>
                            <th>Hab.</th>
                            <th>Paciente</th>
                            <th>Servicio</th>
                            <th>Despachado</th>
                            <th>Despachó</th>
                            @can('confirmReception')
                                <th><span class="sr-only">Acciones</span></th>
                            @endcan
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
                                <td class="dr-patient">
                                    {{ $d->diet?->name_patient }}
                                    @if ($d->diet && in_array($d->meal, $d->diet->transferMealsOn($d->service_date), true))
                                        <div class="dr-transfer">Enviado a {{ $d->diet->transfer_destination }}</div>
                                    @endif
                                </td>
                                <td><span class="tag tag-tiempo">{{ $meals[$d->meal] ?? $d->meal }}</span></td>
                                <td class="dr-when">
                                    @unless ($d->service_date->isToday())
                                        {{ $d->service_date->locale('es')->isoFormat('D MMM') }} ·
                                    @endunless
                                    {{ $d->dispatched_at->format('g:i a') }}
                                </td>
                                <td class="dr-when">{{ $d->dispatchedBy?->name ?? '—' }}</td>
                                @can('confirmReception')
                                    <td class="dr-actions">
                                        <button type="button" class="btn btn-sm btn-ghost dr-missing" wire:click="startNotArrived({{ $d->id }})"
                                            aria-label="No llegó: habitación {{ $d->diet?->habitation }}, {{ $meals[$d->meal] ?? $d->meal }}">
                                            No llegó
                                        </button>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($voiding)
        <x-modal-void-dispatch :dispatch="$voiding" :kind="\App\Models\DietDispatchVoid::NOT_ARRIVED" :meals="$meals"
            confirm="confirmNotArrived" cancel="cancelNotArrived" />
    @endif

    <div class="dr-toast" role="status" aria-live="polite" x-show="toast" x-cloak x-transition
        x-effect="if (toast) { clearTimeout(window.drToastTimer); window.drToastTimer = setTimeout(() => toast = null, 5000) }"
        x-text="toast"></div>

    <style>
        .dr-panel { border: 1px solid var(--color-borde); border-left: 5px solid var(--blue); background: var(--color-primario-suave); border-radius: 10px; padding: 8px 12px; }
        .dr-head { display: flex; align-items: center; gap: 6px 12px; flex-wrap: wrap; }
        .dr-toggle { display: inline-flex; align-items: center; gap: 6px; background: none; border: 0; padding: 2px 0; cursor: pointer; color: var(--color-primario-suave-texto); }
        .dr-chevron { transition: transform .15s; }
        .dr-chevron-open { transform: rotate(90deg); }
        .dr-title { display: flex; align-items: center; gap: 6px; margin: 0; font-size: 14px; font-weight: 700; color: var(--color-primario-suave-texto); }
        .dr-help { font-size: 12px; color: var(--text-muted); }
        .dr-head .dr-confirm { margin-left: auto; }
        .dr-body { margin-top: 8px; max-height: 196px; overflow: auto; border-radius: 8px; background: var(--color-superficie); }
        .dr-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .dr-table th { position: sticky; top: 0; z-index: 1; background: var(--color-progreso-pista); text-align: left; font-size: 11.5px; font-weight: 600; color: var(--text-muted); padding: 5px 8px; border-bottom: 1px solid var(--border); }
        .dr-table td { padding: 4px 8px; border-bottom: 1px solid var(--color-borde); vertical-align: middle; }
        .dr-table .tag { margin: 0; }
        .dr-patient { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dr-transfer { font-size: 11.5px; font-weight: 600; color: var(--blue); white-space: normal; }
        .dr-when { white-space: nowrap; font-size: 12px; color: var(--text-muted); }
        .dr-check { width: 32px; }
        .dr-actions { width: 1%; white-space: nowrap; text-align: right; }
        .dr-missing { padding: 2px 10px; font-size: 12px; }
        .dr-checkbox { width: 16px; height: 16px; cursor: pointer; accent-color: var(--blue); }
        .dr-checkbox:focus-visible, .dr-confirm:focus-visible, .dr-toggle:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .dr-row-selected td { background: var(--color-primario-suave); }
        .dr-confirm { background: var(--btn-primary); color: var(--btn-primary-texto); font-weight: 700; border: 0; }
        .dr-confirm:hover:not([disabled]) { background: var(--btn-primary-hover); }
        .dr-confirm[disabled] { opacity: .5; cursor: not-allowed; }
        .dr-toast { position: fixed; right: 20px; bottom: 20px; z-index: 300; max-width: min(360px, calc(100vw - 32px)); background: var(--text); color: #fff; padding: 12px 16px; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.2); font-size: 13.5px; }
        @media (max-width: 640px) { .dr-head .dr-confirm { width: 100%; justify-content: center; margin-left: 0; } .dr-help { display: none; } }
    </style>
</div>
