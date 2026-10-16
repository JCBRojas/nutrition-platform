@php
    $total = $pending->count();
    $fmtTime = fn ($date) => $date?->locale('es')->isoFormat('D MMM, h:mm a');
    $changeLabel = fn ($v) => $changeLabels[$v?->changes ?? 'NA'] ?? ucfirst(str_replace('_', ' ', (string) $v?->changes));
@endphp
<div class="content" id="page-despacho" wire:poll.3s.keep-alive="checkPending"
    x-data="{ toast: null, flash: false }"
    x-on:diet-changes-arrived.window="toast = 'Nuevo cambio en hab. ' + $event.detail.rooms.join(', ') + '. Confírmalo antes de entregar.'; flash = true; setTimeout(() => flash = false, 2400)"
    x-on:diet-change-confirmed.window="toast = 'Cambio de la hab. ' + $event.detail.room + ' confirmado.'"
    x-on:diet-dispatched.window="toast = $event.detail.message">

    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">

        <div class="dm-head">
            <h1 class="card-title">Despacho de dietas</h1>

            {{-- Alertas del navegador: el permiso y el audio requieren un clic del usuario --}}
            <div wire:ignore x-data="{ perm: 'Notification' in window ? Notification.permission : 'unsupported' }">
                <button type="button" class="btn btn-ghost btn-sm" x-show="perm !== 'granted'"
                    x-on:click="window.dmUnlockAudio(); if (perm !== 'unsupported') Notification.requestPermission().then(p => perm = p)">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/></svg>
                    Activar alertas
                </button>
                <span class="dm-alerts-on" x-show="perm === 'granted'" x-cloak>
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/></svg>
                    Alertas activas
                </span>
            </div>
        </div>

        {{-- ===================== CAMBIOS PENDIENTES ===================== --}}
        <div role="alert" aria-live="assertive">
            @if ($total > 0)
                <section class="dm-alert" :class="flash && 'dm-alert-flash'">
                    <div class="dm-alert-title">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        {{ $total === 1 ? '1 dieta cambió' : "{$total} dietas cambiaron" }}. Revisa y confirma antes de entregar.
                    </div>
                    <ul class="dm-alert-list">
                        @foreach ($pending->take(8) as $p)
                            @php $v = $p->currentVersion; @endphp
                            <li wire:key="pending-{{ $p->id }}-{{ $v?->version }}">
                                <span class="room-badge">{{ $p->habitation }}</span>
                                <span class="dm-alert-who">{{ $p->name_patient }}</span>
                                <span @class(['tag', 'dm-type-closing' => in_array($v?->changes, \App\Models\Diet::CLOSING_CHANGES, true), 'dm-type' => ! in_array($v?->changes, \App\Models\Diet::CLOSING_CHANGES, true)])>
                                    {{ $p->isActive() ? $changeLabel($v) : ($p->status === \App\Models\Diet::FINISHED ? 'Alta: no despachar' : 'Cancelada: no despachar') }}
                                </span>
                                <span class="dm-meta">v{{ $v?->version }} · {{ $fmtTime($v?->created_at) }}</span>
                                <button type="button" class="btn btn-sm dm-confirm" wire:click="confirmChanges({{ $p->id }})"
                                    wire:confirm="¿Confirmas que revisaste el cambio de la habitación {{ $p->habitation }} ({{ $p->name_patient }})? Dejará de aparecer como pendiente."
                                    wire:loading.attr="disabled" wire:target="confirmChanges({{ $p->id }})"
                                    aria-label="Confirmar cambio de la habitación {{ $p->habitation }}">
                                    Confirmar
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    @if ($total > 8)
                        <p class="dm-meta">Y {{ $total - 8 }} más: aparecen primero en la tabla.</p>
                    @endif
                </section>
            @endif
        </div>

        {{-- ===================== FILTROS ===================== --}}
        <div class="dm-toolbar">
            <div class="tabs-wrapper" role="group" aria-label="Filtrar dietas">
                <button type="button" @class(['btn btn-ghost tab-btn', 'active' => $tab === 'active']) aria-pressed="{{ $tab === 'active' ? 'true' : 'false' }}"
                    wire:click="setTab('active')">
                    Dietas activas ({{ $counts['active'] }})
                    @if ($counts['pendingActive'])
                        <span class="dm-count" title="Cambios sin confirmar">{{ $counts['pendingActive'] }}</span>
                    @endif
                </button>
                <button type="button" @class(['btn btn-ghost tab-btn', 'active' => $tab === 'closed']) aria-pressed="{{ $tab === 'closed' ? 'true' : 'false' }}"
                    wire:click="setTab('closed')">
                    Canceladas y finalizadas ({{ $counts['closed'] }})
                    @if ($counts['pendingClosed'])
                        <span class="dm-count" title="Cierres sin confirmar">{{ $counts['pendingClosed'] }}</span>
                    @endif
                </button>
            </div>

            <input type="search" class="filter-input dm-search" placeholder="Buscar habitación o paciente"
                aria-label="Buscar habitación o paciente" wire:model.live.debounce.300ms="search">
        </div>

        {{-- ===================== DESPACHO ===================== --}}
        @if ($tab === 'active')
            @can('confirmDelivery')
                <div class="dm-dispatchbar">
                    <label class="dm-dispatch-meal">
                        <span>Servicio a despachar</span>
                        <select class="filter-input" wire:model.live="meal" aria-label="Tiempo de comida a despachar">
                            @foreach ($meals as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="dm-check-label dm-showall">
                        <input type="checkbox" class="dm-checkbox" wire:model.live="showAll">
                        <span>Mostrar todas las activas</span>
                    </label>
                    <p class="dm-meta dm-dispatch-help" role="status">
                        @if ($counts['applies'] !== null)
                            <strong>{{ $counts['applies'] }} de {{ $counts['active'] }}</strong> dietas aplican a {{ mb_strtolower($meals[$meal] ?? '') }} (se incluyen las que tienen cambios sin confirmar).
                        @endif
                        Marca las dietas listas y pulsa «Despachar». El nutricionista confirma la recepción; solo lo recibido se factura.
                    </p>
                    <button type="button" class="btn btn-sm dm-dispatch-btn" wire:click="dispatchSelected"
                        @disabled(count($selected) === 0)
                        wire:confirm="¿Despachar {{ count($selected) }} dieta(s) de {{ mb_strtolower($meals[$meal] ?? '') }}? Se notificará al nutricionista para que confirme la recepción."
                        wire:loading.attr="disabled" wire:target="dispatchSelected">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 3h15v13H1zM16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        Despachar seleccionadas ({{ count($selected) }})
                    </button>
                </div>
            @endcan
        @endif

        {{-- ===================== TABLA ===================== --}}
        @if ($tab === 'active')
            <x-meal-legend />
        @endif
        <div class="card">
            <div class="table-responsive">
                <table class="table-wrap dm-table">
                    <thead>
                        <tr>
                            @if ($tab === 'active')
                                <th class="dm-check">
                                    @can('confirmDelivery')
                                        @if ($dispatchable)
                                            <input type="checkbox" class="dm-checkbox" aria-label="Seleccionar todas las dietas listas de esta página"
                                                @checked(array_diff($dispatchable, $selected) === [])
                                                wire:click="togglePage(@js($dispatchable))">
                                        @endif
                                    @endcan
                                    <span class="dm-check-title">Despacho</span>
                                </th>
                            @endif
                            <th>Estado</th>
                            <th>Hab.</th>
                            <th>Paciente</th>
                            @if ($tab === 'active')
                                <th>Tiempo</th>
                                <th>Consistencia</th>
                                <th>Especificaciones</th>
                                <th>Observaciones</th>
                                <th>Aislam.</th>
                                <th>Último cambio</th>
                            @else
                                <th>Observaciones</th>
                                <th>Cierre</th>
                                <th>Fecha</th>
                                <th>Por</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($diets as $diet)
                            @php
                                $v = $diet->currentVersion;
                                $chg = $diet->pending_changes;
                                $isPending = (bool) $diet->has_pending;
                            @endphp
                            <tr wire:key="diet-{{ $diet->id }}-{{ $v?->version }}" @class(['dm-row-pending' => $isPending, 'dm-row-selected' => in_array($diet->id, $selected), 'cancelled-row' => $tab === 'closed' && ! $isPending])>
                                {{-- DESPACHO DEL SERVICIO ELEGIDO --}}
                                @if ($tab === 'active')
                                    @php $record = $diet->dispatch_record; @endphp
                                    <td class="dm-check">
                                        @if ($record)
                                            <span @class(['dm-dispatch-state', 'dm-received' => $record->isReceived()])>
                                                {{ $record->isReceived() ? 'Recibida' : 'Despachada' }}
                                            </span>
                                            <div class="dm-meta">
                                                {{ $record->isReceived() ? $record->received_at->format('g:i a') : $record->dispatched_at->format('g:i a').' · esperando recepción' }}
                                            </div>
                                        @elseif ($diet->dispatch_block)
                                            <span class="dm-meta" title="{{ $diet->dispatch_block }}">{{ $diet->dispatch_block }}</span>
                                        @else
                                            @can('confirmDelivery')
                                                <label class="dm-check-label">
                                                    <input type="checkbox" class="dm-checkbox" value="{{ $diet->id }}" wire:model.live="selected"
                                                        aria-label="Lista para despachar: habitación {{ $diet->habitation }}">
                                                    <span>Lista</span>
                                                </label>
                                            @endcan
                                        @endif
                                    </td>
                                @endif
                                {{-- ESTADO / CONFIRMACIÓN --}}
                                <td class="dm-status">
                                    @if ($isPending)
                                        <span class="dm-pending-chip">
                                            <span class="dm-dot" aria-hidden="true"></span>
                                            {{ $tab === 'active' ? 'Cambio sin confirmar' : 'Cierre sin confirmar' }}
                                        </span>
                                        <button type="button" class="btn btn-sm dm-confirm" wire:click="confirmChanges({{ $diet->id }})"
                                            wire:confirm="¿Confirmas que revisaste el cambio de la habitación {{ $diet->habitation }} ({{ $diet->name_patient }})? Dejará de aparecer como pendiente."
                                            wire:loading.attr="disabled" wire:target="confirmChanges({{ $diet->id }})"
                                            aria-label="Confirmar cambio de la habitación {{ $diet->habitation }}">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                            Confirmar
                                        </button>
                                    @else
                                        <span class="dm-ok">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                            Confirmada
                                        </span>
                                    @endif
                                </td>

                                <td><span class="room-badge">{{ $diet->habitation }}</span></td>
                                <td>{{ $diet->name_patient }}</td>

                                @if ($tab === 'active')
                                    <x-dispatch-tags :items="$v?->timeFood ?? []" :labels="$foodLabels" :change="$chg['timeFood'] ?? null" :delivery="$delivery[$diet->id] ?? []" class="tag-tiempo" />
                                    <x-dispatch-tags :items="$v?->consistency ?? []" :labels="$consistencyLabels" :change="$chg['consistency'] ?? null" class="tag-consist" />
                                    <x-dispatch-tags :items="$v?->specifications ?? []" :labels="$specLabels" :change="$chg['specifications'] ?? null" class="tag-espec" />

                                    <td @class(['dm-changed-cell' => isset($chg['observations'])])>
                                        {{ $v?->observations ?: '—' }}
                                        @isset($chg['observations'])
                                            <div class="dm-before">Antes: {{ $chg['observations']['old'] ?: '—' }}</div>
                                        @endisset
                                    </td>
                                    <td @class(['dm-changed-cell' => isset($chg['isolation'])])>
                                        <span @class(['dm-isolation' => $v?->isolation === 'Sí'])>{{ $v?->isolation ?? 'No' }}</span>
                                        @isset($chg['isolation'])
                                            <div class="dm-before">Antes: {{ $chg['isolation']['old'] ?? 'No' }}</div>
                                        @endisset
                                    </td>
                                    <td class="dm-last">
                                        <span class="tag dm-type">{{ $changeLabel($v) }}</span>
                                    </td>
                                @else
                                    <td>{{ $v?->observations ?: '—' }}</td>
                                    <td>
                                        @if ($diet->status === \App\Models\Diet::FINISHED)
                                            <span class="tag dm-tag-finished">Finalizada (alta)</span>
                                        @else
                                            <span class="tag tag-cancel">Cancelada</span>
                                        @endif
                                    </td>
                                    <td>{{ $fmtTime($diet->updated_at) }}</td>
                                    <td>{{ $v?->updatedBy?->name ?? '—' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $tab === 'active' ? 10 : 7 }}" class="empty">
                                    @if ($search !== '')
                                        No hay dietas que coincidan con «{{ $search }}».
                                    @else
                                        @if ($tab === 'active' && $counts['applies'] === 0 && $counts['active'] > 0)
                                            Ninguna dieta activa incluye {{ mb_strtolower($meals[$meal] ?? '') }}.
                                        @else
                                            {{ $tab === 'active' ? 'No hay dietas activas.' : 'No hay dietas canceladas ni finalizadas.' }}
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $diets->onEachSide(1)->links('pagination.table', ['livewire' => true]) }}
        </div>
    </div>

    {{-- Aviso breve (nuevo cambio / confirmación) --}}
    <div class="dm-toast" role="status" aria-live="polite" x-show="toast" x-cloak x-transition
        x-effect="if (toast) { clearTimeout(window.dmToastTimer); window.dmToastTimer = setTimeout(() => toast = null, 5000) }"
        x-text="toast"></div>

    <style>
        .dm-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .dm-alerts-on { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--text-muted); }

        /* Banner de cambios pendientes */
        .dm-alert { border: 2px solid #bfdbfe; border-left: 8px solid var(--blue); background: #eff6ff; border-radius: 10px; padding: 14px 16px; margin-bottom: 16px; }
        .dm-alert-flash { animation: dm-flash .6s ease-in-out 4; }
        .dm-alert-title { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 15px; color: #1e3a8a; margin-bottom: 10px; }
        .dm-alert-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
        .dm-alert-list li { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; background: #fff; border-radius: 8px; padding: 6px 10px; }
        .dm-alert-who { font-weight: 600; }
        .dm-alert-list .dm-confirm { margin-left: auto; background: var(--blue); }

        .dm-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .dm-toolbar .tabs-wrapper { display: flex; gap: 8px; flex-wrap: wrap; }
        .dm-search { max-width: 280px; }
        .tab-btn.active { background: var(--blue-light); color: var(--blue); border-color: #bfdbfe; font-weight: 600; }
        .tab-btn:focus-visible, .dm-confirm:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .dm-count { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: var(--amber); color: #fff; font-size: 11.5px; font-weight: 700; }

        /* Filas */
        .dm-table td { vertical-align: top; }
        .dm-row-pending td { background: var(--amber-light); }
        .dm-row-pending td:first-child { box-shadow: inset 6px 0 0 var(--amber); }
        .dm-status { min-width: 150px; }
        .dm-pending-chip { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: 12px; color: #8a4b00; margin-bottom: 6px; }
        .dm-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--amber); animation: dm-pulse 1.4s ease-in-out infinite; }
        .dm-confirm { background: var(--amber); color: #fff; font-weight: 700; border: 0; }
        .dm-confirm:hover { filter: brightness(.92); }
        .dm-confirm[disabled] { opacity: .6; }
        .dm-ok { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: var(--text-muted); }
        .dm-changed-cell { outline: 2px dashed var(--amber); outline-offset: -4px; }
        .dm-added { background: #dcfce7; color: #166534; font-weight: 700; }
        .dm-removed { background: var(--accent2-light); color: var(--accent2); }
        .dm-before { margin-top: 4px; font-size: 11.5px; color: var(--text-muted); text-decoration: line-through; }
        .dm-isolation { font-weight: 700; color: var(--accent2); }
        .dm-type { background: var(--surface2); color: var(--text); font-weight: 600; }
        .dm-type-closing { background: var(--accent2-light); color: var(--accent2); font-weight: 700; }
        .dm-tag-finished { background: var(--blue-light); color: var(--blue); font-weight: 600; }
        .dm-meta { font-size: 11.5px; color: var(--text-muted); }

        .dm-toast { position: fixed; right: 20px; bottom: 20px; z-index: 300; max-width: min(360px, calc(100vw - 32px)); background: var(--text); color: #fff; padding: 12px 16px; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.2); font-size: 13.5px; }

        @keyframes dm-pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.5); opacity: .5; } }
        @keyframes dm-flash { 0%, 100% { box-shadow: none; } 50% { box-shadow: 0 0 0 6px rgba(74, 127, 203, .35); } }

        /* Despacho */
        .dm-dispatchbar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 10px 12px; margin-bottom: 12px; border: 1px solid var(--border, #e5e7eb); border-radius: 10px; background: var(--surface2, #f8fafc); }
        .dm-dispatch-meal { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; }
        .dm-dispatch-meal select { min-width: 150px; }
        .dm-dispatch-help { flex: 1 1 240px; margin: 0; }
        .dm-dispatch-help strong { color: var(--text); }
        .dm-showall { font-weight: 600; }
        .dm-dispatch-btn { background: var(--accent, #2f855a); color: #fff; font-weight: 700; border: 0; }
        .dm-dispatch-btn[disabled] { opacity: .5; cursor: not-allowed; }
        .dm-dispatch-btn:focus-visible, .dm-checkbox:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .dm-check { min-width: 110px; }
        .dm-check-title { margin-left: 6px; }
        .dm-check-label { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; cursor: pointer; }
        .dm-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: var(--accent, #2f855a); }
        .dm-dispatch-state { display: inline-block; font-size: 12px; font-weight: 700; color: var(--blue); }
        .dm-dispatch-state.dm-received { color: #166534; }
        .dm-row-selected td { background: #f0fdf4; }
        @media (prefers-reduced-motion: reduce) { .dm-dot, .dm-alert-flash { animation: none; } }
        @media (max-width: 640px) { .dm-search { max-width: none; width: 100%; } .dm-alert-list .dm-confirm { margin-left: 0; } }
    </style>
</div>

@script
<script>
    // Sonido corto con Web Audio (sin archivos); se habilita tras el primer clic del usuario
    let audio = null;
    window.dmUnlockAudio = () => {
        try { audio ??= new (window.AudioContext || window.webkitAudioContext)(); audio.resume(); } catch (e) {}
    };
    document.addEventListener('click', window.dmUnlockAudio, { once: true });

    const beep = () => {
        if (!audio || audio.state !== 'running') return;
        [0, 0.25].forEach((delay) => {
            const osc = audio.createOscillator();
            const gain = audio.createGain();
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.2, audio.currentTime + delay);
            gain.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + delay + 0.2);
            osc.connect(gain).connect(audio.destination);
            osc.start(audio.currentTime + delay);
            osc.stop(audio.currentTime + delay + 0.2);
        });
    };

    $wire.on('diet-changes-arrived', ({ rooms }) => {
        beep();
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Cambio de dieta', {
                body: 'Hab. ' + rooms.join(', ') + ': confirma el cambio antes de entregar.',
                tag: 'diet-changes',
                renotify: true,
            });
        }
    });

    // Contador en la pestaña del navegador
    const baseTitle = document.title.replace(/^\(\d+\)\s.*?·\s/, '');
    const setTitle = (n) => { document.title = n ? `(${n}) Cambios sin confirmar · ${baseTitle}` : baseTitle; };
    setTitle($wire.pendingCount);
    $wire.$watch('pendingCount', setTitle);
</script>
@endscript
