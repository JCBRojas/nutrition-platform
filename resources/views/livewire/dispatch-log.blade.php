@php
    $totals = ['dispatched' => array_sum(array_column($summary, 'dispatched')), 'received' => array_sum(array_column($summary, 'received'))];
    $isToday = $day->isToday();
@endphp
{{-- Tiempo real: se actualiza al llegar un aviso de despachos (resources/js/live.js); sin WebSocket, cada 10 s --}}
<div class="content" id="page-despachos" x-data
    x-init="window.ntLive(() => $wire.$refresh(), { kinds: ['dispatch'], fallbackMs: 10000 })">
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">

        <div class="dl-head">
            <div>
                <h1 class="card-title">Despachos del día</h1>
                <p class="dl-sub">{{ ucfirst($day->locale('es')->isoFormat('dddd D [de] MMMM YYYY')) }}{{ $isToday ? ' · hoy' : '' }}</p>
            </div>
            <label class="dl-date">
                <span>Fecha</span>
                <input type="date" class="filter-input" max="{{ now()->toDateString() }}"
                    wire:model.live="date" aria-label="Fecha de los despachos">
            </label>
        </div>

        {{-- ===================== RESUMEN ===================== --}}
        <div class="stats-grid dl-stats">
            <div class="stat-card blue">
                <div class="stat-label">Despachadas</div>
                <div class="stat-value">{{ $totals['dispatched'] }}</div>
                <div class="stat-sub">Bandejas que salieron de cocina</div>
            </div>
            <div class="stat-card green">
                <div class="stat-label">Entregadas</div>
                <div class="stat-value">{{ $totals['received'] }}</div>
                <div class="stat-sub">Recepción confirmada por el nutricionista</div>
            </div>
            <div class="stat-card amber">
                <div class="stat-label">Sin confirmar</div>
                <div class="stat-value">{{ $totals['dispatched'] - $totals['received'] }}</div>
                <div class="stat-sub">No cuentan en reportes ni facturación</div>
            </div>
        </div>

        <div class="dl-meals" role="group" aria-label="Filtrar por tiempo de comida">
            @foreach ($summary as $key => $s)
                @continue($s['dispatched'] === 0 && ! $s['planned'])
                <button type="button" wire:click="filterMeal('{{ $key }}')" @class(['dl-meal', 'active' => $meal === $key])
                    aria-pressed="{{ $meal === $key ? 'true' : 'false' }}">
                    <span class="dl-meal-name">{{ $s['label'] }}</span>
                    <span class="dl-meal-nums">
                        <strong>{{ $s['received'] }}</strong> entregadas · {{ $s['dispatched'] }} despachadas
                        @if ($s['planned'] !== null)
                            · {{ $s['planned'] }} programadas
                        @endif
                    </span>
                    @if ($s['planned'])
                        <span class="dl-bar" aria-hidden="true">
                            <span class="dl-bar-sent" style="width: {{ min(100, round($s['dispatched'] / $s['planned'] * 100)) }}%"></span>
                            <span class="dl-bar-ok" style="width: {{ min(100, round($s['received'] / $s['planned'] * 100)) }}%"></span>
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- ===================== FILTROS ===================== --}}
        <div class="dl-toolbar">
            <select class="filter-input" wire:model.live="meal" aria-label="Tiempo de comida">
                <option value="">Todos los tiempos</option>
                @foreach ($meals as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <select class="filter-input" wire:model.live="status" aria-label="Estado">
                <option value="">Todos los estados</option>
                <option value="pending">Despachadas sin recibir</option>
                <option value="received">Entregadas (recibidas)</option>
            </select>
            <input type="search" class="filter-input dl-search" placeholder="Buscar habitación o paciente"
                aria-label="Buscar habitación o paciente" wire:model.live.debounce.300ms="search">
            <x-meal-legend class="dl-legend" />
        </div>

        {{-- ===================== TABLA ===================== --}}
        <div class="card">
            <div class="table-responsive">
                <table class="table-wrap">
                    <thead>
                        <tr>
                            <th>Hab.</th>
                            <th>Paciente</th>
                            <th>Tiempo</th>
                            <th>Despachó</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $d)
                            <tr wire:key="dl-{{ $d->id }}">
                                <td><span class="room-badge">{{ $d->diet?->habitation }}</span></td>
                                <td>{{ $d->diet?->name_patient }}</td>
                                <td><x-meal-tag :label="$meals[$d->meal] ?? $d->meal" :status="$d->isReceived() ? 'received' : 'dispatched'" /></td>
                                <td>
                                    {{ $d->dispatchedBy?->name ?? '—' }}
                                    <div class="dl-meta">{{ $d->dispatched_at->format('g:i a') }}</div>
                                </td>
                                <td>
                                    @if ($d->isReceived())
                                        <span class="dl-ok">Entregada</span>
                                        <div class="dl-meta">{{ $d->receivedBy?->name ?? '—' }} · {{ $d->received_at->format('g:i a') }}</div>
                                    @else
                                        <span class="dl-wait">Esperando recepción</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty">
                                    @if ($search !== '' || $meal !== '' || $status !== '')
                                        No hay despachos que coincidan con los filtros.
                                    @else
                                        {{ $isToday ? 'Aún no hay despachos hoy.' : 'No hubo despachos ese día.' }}
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $rows->onEachSide(1)->links('pagination.table', ['livewire' => true]) }}
        </div>
    </div>

    <style>
        .dl-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .dl-sub { margin: 2px 0 0; font-size: 13px; color: var(--text-muted); }
        .dl-date { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; }
        .dl-stats { margin-bottom: 14px; }
        .dl-meals { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 8px; margin-bottom: 14px; }
        .dl-meal { display: grid; gap: 4px; text-align: left; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); cursor: pointer; }
        .dl-meal:hover { background: var(--bg); }
        .dl-meal.active { border-color: #0f766e; box-shadow: inset 0 0 0 1px #0f766e; }
        .dl-meal:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .dl-meal-name { font-weight: 700; font-size: 13.5px; }
        .dl-meal-nums { font-size: 12px; color: var(--text-muted); }
        .dl-meal-nums strong { color: #0f766e; }
        .dl-bar { position: relative; height: 6px; border-radius: 999px; background: var(--surface2); overflow: hidden; }
        .dl-bar > span { position: absolute; inset: 0 auto 0 0; border-radius: 999px; }
        .dl-bar-sent { background: #99d5cf; }
        .dl-bar-ok { background: #0f766e; }
        .dl-toolbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
        .dl-search { max-width: 260px; }
        .dl-legend { margin: 0 0 0 auto; }
        .dl-meta { font-size: 11.5px; color: var(--text-muted); }
        .dl-ok { font-weight: 700; color: #0f766e; font-size: 12.5px; }
        .dl-wait { font-weight: 600; color: var(--amber); font-size: 12.5px; }
        @media (max-width: 640px) { .dl-search { max-width: none; flex: 1 1 100%; } .dl-legend { margin-left: 0; } }
    </style>
</div>
