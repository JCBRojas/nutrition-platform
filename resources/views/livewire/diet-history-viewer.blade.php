<div class="content" id="page-historial">
    <style>
        .hv-grid { display: grid; grid-template-columns: minmax(0, 300px) minmax(0, 1fr); gap: 20px; align-items: start; }
        .hv-list { max-height: 72vh; overflow-y: auto; }
        .hv-item { display: block; width: 100%; text-align: left; padding: 10px 14px; border: 0; border-bottom: 1px solid var(--border); background: var(--surface); cursor: pointer; }
        .hv-item:hover { background: var(--surface2); }
        .hv-item[aria-current="true"] { background: var(--accent-light); box-shadow: inset 3px 0 0 var(--accent); }
        .hv-item .hv-name { font-weight: 600; color: var(--text); }
        .hv-item .hv-meta { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
        .hv-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 16px 18px; border-bottom: 1px solid var(--border); }
        .hv-head h2 { font-size: 18px; font-weight: 700; margin: 0; }
        .hv-table-wrap { overflow-x: auto; }
        .vtable td { vertical-align: top; }
        .vtable tr.vrow-finish td { background: var(--blue-light); }
        .vtable tr.vrow-finish td:first-child { border-left: 3px solid var(--blue); }
        .vtable tr.vrow-day td { background: var(--color-exito-suave); }
        .vtable tr.vrow-day td:first-child { border-left: 3px solid var(--color-exito); }
        .vtable td.hv-changed { box-shadow: inset 0 0 0 2px var(--amber); }
        .chip-removed { text-decoration: line-through; opacity: .75; background: var(--accent2-light); color: var(--accent2); }
        .chip-added { background: var(--accent-light); color: var(--accent); font-weight: 600; }
        .hv-legend { display: flex; gap: 14px; flex-wrap: wrap; font-size: 12px; color: var(--text-muted); padding: 10px 18px; border-top: 1px solid var(--border); }
        .hv-legend span { display: inline-flex; align-items: center; gap: 6px; }
        @media (max-width: 900px) { .hv-grid { grid-template-columns: minmax(0, 1fr); } .hv-list { max-height: 40vh; } }
    </style>

    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
        <h1 class="card-title mb-4">Historial de cambios</h1>

        <div class="hv-grid">
            {{-- ===================== SELECTOR DE DIETA ===================== --}}
            <div class="card">
                <div class="filters-bar" style="margin:0;padding:12px;flex-direction:column;align-items:stretch;gap:8px">
                    <input type="search" class="filter-input" placeholder="Buscar habitación o paciente"
                        aria-label="Buscar habitación o paciente" wire:model.live.debounce.300ms="search">
                    <select class="filter-select" wire:model.live="status" aria-label="Estado">
                        <option value="all">Todas</option>
                        <option value="active">Activas</option>
                        <option value="cancelled">Canceladas</option>
                        <option value="finished">Finalizadas</option>
                    </select>
                </div>

                <div class="hv-list" role="list">
                    @forelse ($diets as $diet)
                        <button type="button" class="hv-item" role="listitem" wire:key="diet-{{ $diet->id }}"
                            wire:click="select({{ $diet->id }})" aria-current="{{ $selected?->id === $diet->id ? 'true' : 'false' }}">
                            <div class="hv-name">
                                <span class="room-badge">{{ $diet->habitation }}</span>
                                {{ $diet->name_patient }}
                            </div>
                            <div class="hv-meta">
                                {{ $diet->history_count }} {{ $diet->history_count === 1 ? 'registro' : 'registros' }} ·
                                {{ $diet->statusLabel() }} ·
                                {{ $diet->updated_at?->format('d/m/Y') }}
                            </div>
                        </button>
                    @empty
                        <div class="empty" style="padding:24px">No hay dietas con estos filtros.</div>
                    @endforelse
                </div>
                {{ $diets->onEachSide(0)->links('pagination.table', ['livewire' => true]) }}
            </div>

            {{-- ===================== LÍNEA DE TIEMPO ===================== --}}
            <div class="card">
                @if ($selected)
                    <div class="hv-head">
                        <h2>
                            <span class="room-badge">{{ $selected->habitation }}</span>
                            {{ $selected->name_patient }}
                        </h2>
                        <span class="text-sm text-slate-500">
                            {{ $timeline->count() }} {{ $timeline->count() === 1 ? 'registro' : 'registros' }} ·
                            @if ($selected->isActive())
                                <span class="badge badge-success">Activa</span>
                            @elseif ($selected->status === \App\Models\Diet::FINISHED)
                                <span class="tag" style="background:var(--blue-light);color:var(--blue);font-weight:600">Finalizada</span>
                            @else
                                <span class="tag tag-cancel">Cancelada</span>
                            @endif
                        </span>
                    </div>

                    <div class="hv-table-wrap">
                        <table class="vtable">
                            <thead>
                                <tr>
                                    <th>Registro</th>
                                    <th>Fecha</th>
                                    <th>Tiempo</th>
                                    <th>Consistencia</th>
                                    <th>Especificaciones</th>
                                    <th>Tipo (facturación)</th>
                                    <th>Observaciones</th>
                                    <th>Aislam.</th>
                                    <th>Registró</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($timeline as $row)
                                    @php
                                        $v = $row['version'];
                                        $f = $row['fields'];
                                        $rowClass = ['creacion' => 'vrow-create', 'edicion' => 'vrow-edit', 'cancelacion' => 'vrow-cancel', 'finalizacion' => 'vrow-finish', 'completada' => 'vrow-day', 'continuacion' => 'vrow-day'][$row['type']];
                                        $typeLabel = ['creacion' => 'Creación', 'edicion' => 'Edición', 'cancelacion' => 'Cancelación', 'finalizacion' => 'Alta (finalizada)', 'completada' => 'Día completado', 'continuacion' => 'Continuada (día siguiente)'][$row['type']];
                                        $tagClass = ['timeFood' => 'tag-tiempo', 'consistency' => 'tag-consist', 'specifications' => 'tag-espec'];
                                    @endphp
                                    <tr class="{{ $rowClass }}" wire:key="v-{{ $v->id }}">
                                        <td style="white-space:nowrap">
                                            <strong>{{ $typeLabel }}</strong>
                                            @if ($v->changes && ! in_array($v->changes, ['NA', ...\App\Models\Diet::CLOSING_CHANGES, ...\App\Models\Diet::DAY_CHANGES], true))
                                                <div class="text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $v->changes)) }}</div>
                                            @endif
                                        </td>
                                        <td style="white-space:nowrap">
                                            {{ $v->created_at->format('d/m/Y') }}
                                            <div class="text-xs text-slate-500">{{ $v->created_at->format('h:i a') }}</div>
                                        </td>

                                        @foreach (['timeFood', 'consistency', 'specifications'] as $field)
                                            <td @class(['hv-changed' => $f[$field]['changed']])>
                                                @forelse ($f[$field]['items'] as $item)
                                                    <span @class([
                                                        'tag',
                                                        $tagClass[$field] => $item['state'] === 'kept',
                                                        'chip-added' => $item['state'] === 'added',
                                                        'chip-removed' => $item['state'] === 'removed',
                                                    ])>
                                                        @if ($item['state'] === 'added')<span class="sr-only">Agregado: </span>@endif
                                                        @if ($item['state'] === 'removed')<span class="sr-only">Quitado: </span>@endif
                                                        {{ $item['label'] }}
                                                    </span>
                                                @empty
                                                    <span class="text-slate-500">—</span>
                                                @endforelse
                                            </td>
                                        @endforeach

                                        <td @class(['hv-changed' => $f['billing_type']['changed']])>
                                            @if ($f['billing_type']['changed'] && $f['billing_type']['old'])
                                                <span class="diff-old">{{ $f['billing_type']['old'] }}</span>
                                            @endif
                                            <span @class(['diff-new' => $f['billing_type']['changed']])>{{ $f['billing_type']['value'] ?? 'Sin tipo' }}</span>
                                        </td>
                                        <td @class(['hv-changed' => $f['observations']['changed']])>
                                            @if ($f['observations']['changed'] && $f['observations']['old'])
                                                <span class="diff-old">{{ $f['observations']['old'] }}</span>
                                            @endif
                                            <span @class(['diff-new' => $f['observations']['changed']])>{{ $f['observations']['value'] ?? '—' }}</span>
                                        </td>
                                        <td @class(['hv-changed' => $f['isolation']['changed']]) style="white-space:nowrap">
                                            @if ($f['isolation']['changed'])
                                                <span class="diff-old">{{ $f['isolation']['old'] }}</span>
                                            @endif
                                            <span @class(['diff-new' => $f['isolation']['changed']])>{{ $f['isolation']['value'] }}</span>
                                        </td>
                                        <td>{{ $v->updatedBy?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="hv-legend">
                        <span><span class="tag chip-added">Agregado</span></span>
                        <span><span class="tag chip-removed">Quitado</span></span>
                        <span><span class="diff-old">Antes</span><span class="diff-new">Después</span></span>
                        <span>Recuadro ámbar: campo modificado en ese registro</span>
                    </div>
                @else
                    <div class="empty">
                        Selecciona una dieta de la lista para ver su historial.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
