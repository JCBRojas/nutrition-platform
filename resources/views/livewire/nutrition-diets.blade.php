@php
    // Etiquetas del select "Cambios en la dieta" (components/form-diet.blade.php)
    $changeLabels = ['NA' => '—', 'con_cambios' => 'Con cambios', 'aislado' => 'Se aisló', 'ya_no_aislado' => 'Ya no está aislado', 'cancelada' => 'Cancelada', 'finalizada' => 'Finalizada (alta)', 'completada' => '—', 'continuada' => '—'];
    $closedTabs = [
        'cancelled' => ['date' => 'Cancelada', 'tag' => 'tag-cancel', 'label' => 'Cancelada', 'empty' => 'No hay dietas canceladas.'],
        'finished' => ['date' => 'Alta', 'tag' => 'tag-finished', 'label' => 'Finalizada', 'empty' => 'No hay dietas finalizadas. Usa el botón de alta en una dieta activa cuando el paciente salga de la institución.'],
    ];
    $tabNames = ['active' => 'Activas', 'cancelled' => 'Canceladas', 'finished' => 'Finalizadas'];
@endphp
{{--
    Tiempo real: se refresca al llegar DietsChanged (resources/js/live.js); sin WebSocket, cada 5 s.
    Se aplaza mientras hay un modal o un selector de chips abierto (para no interrumpir una
    edición) o la pestaña está oculta, y se aplica al cerrarlo.
--}}
<div x-data x-init="window.ntLive(() => $wire.$refresh(), { fallbackMs: 5000, canRun: () => ! document.querySelector('.overlay.open, .chip-select.open') })">
    <div class="stats-grid">
        <div class="stat-card green">
            <div class="stat-label">Dietas activas</div>
            <div class="stat-value">{{ $counts['active'] }}</div>
            <div class="stat-sub">Pacientes en seguimiento</div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Canceladas</div>
            <div class="stat-value">{{ $counts['cancelled'] }}</div>
            <div class="stat-sub">{{ $counts['finished'] }} finalizadas por alta</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">En aislamiento</div>
            <div class="stat-value">{{ $stats['isolated'] }}</div>
            <div class="stat-sub">Dietas activas con protocolo especial</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-label">Con cambios</div>
            <div class="stat-value">{{ $stats['changes'] }}</div>
            <div class="stat-sub">Activas cuya última versión trae cambios</div>
        </div>
    </div>

    @if ($notice)
        <div @class(['nd-notice', 'error' => $noticeError]) role="status">{{ $notice }}</div>
    @endif

    {{-- Pestañas y «Registrar Dieta» en una fila flexible (sin float): en móvil el botón ocupa su propia fila --}}
    <div class="tabs-wrapper nd-tabs mb-4">
        @foreach ($tabNames as $key => $name)
            <button type="button" @class(['btn btn-ghost tab-btn', 'active' => $tab === $key])
                aria-pressed="{{ $tab === $key ? 'true' : 'false' }}" wire:click="setTab('{{ $key }}')">
                {{ $name }} ({{ $counts[$key] }})
            </button>
        @endforeach

        @can('createDiets')
            <button type="button" class="btn btn-primary nd-register" id="btnRegistrar" onclick="openCreateModal()">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Registrar Dieta
            </button>
        @endcan
    </div>

    <div class="card">
        {{-- Búsqueda en tiempo real (vuelve a la página 1) --}}
        <div class="nd-search" role="search">
            <input type="search" class="filter-input" wire:model.live.debounce.300ms="search"
                placeholder="Buscar habitación o paciente" aria-label="Buscar dietas {{ mb_strtolower($tabNames[$tab]) }} por habitación o paciente">
            <span class="nd-search-spinner" wire:loading.delay wire:target="search" aria-hidden="true"></span>
            @if (trim($search) !== '')
                <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('search', '')">Limpiar</button>
                <span class="nd-search-info" role="status">{{ $diets->total() }} resultado(s) para «{{ trim($search) }}»</span>
            @endif
            @if ($tab === 'active')
                <x-meal-legend class="nd-legend" />
            @endif
        </div>

        <div class="table-responsive">
            @if ($tab === 'active')
                <table class="table-wrap">
                    <thead>
                        <tr>
                            <th>Hab.</th>
                            <th>Paciente</th>
                            <th>Día</th>
                            <th>Tiempo</th>
                            <th>Consistencia</th>
                            <th>Especificaciones</th>
                            <th>Observaciones</th>
                            <th>Aislam.</th>
                            <th>Cambios</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($diets as $diet)
                            <tr wire:key="active-{{ $diet->id }}-v{{ $diet->currentVersion?->version }}">
                                <td>
                                    <span class="room-badge">{{ $diet->habitation }}</span>
                                </td>

                                <td>{{ $diet->name_patient }}</td>

                                {{-- Estado del día y autorización de mañana --}}
                                @php $day = $dayStatus[$diet->id] ?? []; @endphp
                                <td>
                                    <div class="nd-day">
                                        @if ($day['completed'] ?? false)
                                            <span class="tag tag-completed" title="Se recibieron todos los tiempos de hoy">Completada</span>
                                        @elseif ($day['authorizedToday'] ?? false)
                                            <span class="tag tag-in-progress" title="Autorizada para hoy; faltan tiempos por recibir">En curso</span>
                                        @else
                                            <span class="tag tag-blocked" title="No se puede despachar hoy: no se continuó ayer">Sin autorizar hoy</span>
                                        @endif

                                        @if ($day['authorizedTomorrow'] ?? false)
                                            <span class="nd-next nd-next-ok" title="Continúa mañana: cocina puede despacharla" aria-label="Continúa mañana">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                                Mañana
                                            </span>
                                        @else
                                            @can('continueDiets')
                                            <button type="button" class="nd-next nd-continue" title="Continuar dieta: autoriza el despacho de mañana"
                                                    wire:click="continueDiet({{ $diet->id }})" wire:loading.attr="disabled" wire:target="continueDiet({{ $diet->id }})"
                                                    wire:confirm="{{ "¿Continuar la dieta de {$diet->name_patient} (hab. {$diet->habitation}) mañana?

Cocina podrá despacharla el día siguiente." }}"
                                                    aria-label="Continuar dieta de {{ $diet->name_patient }} mañana">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="13 6 19 12 13 18"/><line x1="5" y1="12" x2="19" y2="12" style="display: block"/></svg>
                                                    Continuar
                                                </button>
                                            @else
                                                <span class="nd-next nd-next-off" title="Mañana sin autorizar">Mañana —</span>
                                            @endcan
                                        @endif
                                    </div>
                                </td>

                                {{-- Tiempo de comida --}}
                                <td>
                                    @php
                                        $items = $diet->currentVersion->timeFood ?? [];
                                    @endphp

                                    @forelse ($items as $item)
                                        <x-meal-tag :label="$foodLabels[$item] ?? ucfirst(str_replace('_', ' ', $item))"
                                            :status="$delivery[$diet->id][str_replace('_', '', mb_strtolower($item))] ?? null" />
                                    @empty
                                        <span>—</span>
                                    @endforelse
                                </td>

                                {{-- Consistencia --}}
                                <td>
                                    @php
                                        $items = $diet->currentVersion->consistency ?? [];
                                    @endphp

                                    @forelse ($items as $item)
                                        <span class="tag tag-consist">
                                            {{ $consistencyLabels[$item] ?? '' }}
                                        </span>
                                    @empty
                                        <span>—</span>
                                    @endforelse
                                </td>

                                {{-- Especificaciones --}}
                                <td>
                                    @php
                                        $items = $diet->currentVersion->specifications ?? [];
                                    @endphp

                                    @forelse ($items as $item)
                                        <span class="tag tag-espec">
                                            {{ $specLabels[$item] ?? '' }}
                                        </span>
                                    @empty
                                        <span>—</span>
                                    @endforelse
                                </td>

                                <td>{{ $diet->currentVersion->observations ?? '—' }}</td>
                                <td>{{ $diet->currentVersion->isolation ?? 'No' }}</td>
                                <td>{{ $changeLabels[$diet->currentVersion->changes ?? 'NA'] ?? ucfirst(str_replace('_', ' ', $diet->currentVersion->changes)) }}</td>

                                <td class="text-right">
                                    <div class="row-actions">
                                        @can('viewDiets')
                                            @premium('history')
                                                <a href="{{ route('diets.history', ['dieta' => $diet->id]) }}"
                                                    class="btn btn-ghost btn-sm btn-icon" title="Ver historial"
                                                    aria-label="Ver historial de {{ $diet->name_patient }}">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/></svg>
                                                </a>
                                            @endpremium
                                        @endcan
                                        @can('editDiets')
                                        <button type="button" class="btn btn-success btn-sm btn-icon" title="Editar dieta"
                                            aria-label="Editar dieta de {{ $diet->name_patient }}"
                                            onclick='openEditModal({{ $diet->id }}, @json($diet))'>
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
                                        </button>
                                        <form method="post" action="{{ route('diets.finish', $diet->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-info btn-sm btn-icon" title="Finalizar dieta (alta del paciente)"
                                                aria-label="Finalizar dieta de {{ $diet->name_patient }} por alta"
                                                onclick="return confirm({{ \Illuminate\Support\Js::from("¿Dar de alta a {$diet->name_patient} (hab. {$diet->habitation})?\n\nLa dieta pasa a Finalizadas y deja de verse en dietas activas. El registro y su historial se conservan.") }})">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                            </button>
                                        </form>
                                        <form method="post" action="{{ route('diets.canceled', $diet->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Cancelar dieta"
                                                aria-label="Cancelar dieta de {{ $diet->name_patient }}"
                                                onclick="return confirm({{ \Illuminate\Support\Js::from("¿Cancelar la dieta de {$diet->name_patient} (hab. {$diet->habitation})?") }})">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                            </button>
                                        </form>
                                        @endcan
                                    </div>
                                    @can('editDiets')
                                        {{-- wire:ignore: el refresco no toca el modal; si la dieta cambia de versión, la fila (y el modal) se recrean --}}
                                        <div class="overlay" id="modalEditOverlay{{ $diet->id }}" wire:ignore>
                                            <x-modal-edit-diets :diet="$diet" :specLabels="$specLabels"
                                                :consistencyLabels="$consistencyLabels" :foodLabels="$foodLabels" />
                                        </div>
                                    @endcan
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="empty">{{ trim($search) !== '' ? 'No hay dietas activas que coincidan con «'.trim($search).'».' : 'No hay dietas activas.' }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                @php $closedTab = $closedTabs[$tab]; @endphp
                <table class="table-wrap">
                    <thead>
                        <tr>
                            <th>Hab.</th>
                            <th>Paciente</th>
                            <th>Tiempo</th>
                            <th>Consistencia</th>
                            <th>Observaciones</th>
                            <th>{{ $closedTab['date'] }}</th>
                            <th>Por</th>
                            <th class="text-right">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($diets as $diet)
                            @php $version = $diet->currentVersion; @endphp
                            <tr class="cancelled-row" wire:key="closed-{{ $diet->id }}">
                                <td><span class="room-badge">{{ $diet->habitation }}</span></td>
                                <td>{{ $diet->name_patient }}</td>
                                <td>
                                    @forelse ($version->timeFood ?? [] as $item)
                                        <span class="tag tag-tiempo">{{ $foodLabels[$item] ?? $item }}</span>
                                    @empty
                                        <span>—</span>
                                    @endforelse
                                </td>
                                <td>
                                    @forelse ($version->consistency ?? [] as $item)
                                        <span class="tag tag-consist">{{ $consistencyLabels[$item] ?? $item }}</span>
                                    @empty
                                        <span>—</span>
                                    @endforelse
                                </td>
                                <td>{{ $version->observations ?? '—' }}</td>
                                <td>{{ $diet->updated_at?->format('d/m/Y h:i a') }}</td>
                                <td>{{ $version?->updatedBy?->name ?? '—' }}</td>
                                <td class="text-right">
                                    <div class="row-actions">
                                        <span class="tag {{ $closedTab['tag'] }}">{{ $closedTab['label'] }}</span>
                                        @can('viewDiets')
                                            @premium('history')
                                                <a href="{{ route('diets.history', ['dieta' => $diet->id]) }}" class="btn btn-ghost btn-sm btn-icon"
                                                    title="Ver historial" aria-label="Ver historial de {{ $diet->name_patient }}">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/></svg>
                                                </a>
                                            @endpremium
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="empty">{{ trim($search) !== '' ? 'No hay dietas que coincidan con «'.trim($search).'».' : $closedTab['empty'] }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        {{ $diets->onEachSide(1)->links('pagination.table', ['livewire' => true]) }}
    </div>
</div>
