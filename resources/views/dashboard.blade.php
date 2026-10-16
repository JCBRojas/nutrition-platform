<x-app-layout>

    <div class="content" id="page-reportes">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">

            @php
                // $activeDiets, $cancelledDiets y $finishedDiets son paginadores (ver DietController::nutritionDashboard)
                // Etiquetas de components/form-diet.blade.php (select "Cambios en la dieta")
                $changeLabels = ['NA' => '—', 'con_cambios' => 'Con cambios', 'aislado' => 'Se aisló', 'ya_no_aislado' => 'Ya no está aislado', 'cancelada' => 'Cancelada', 'finalizada' => 'Finalizada (alta)'];
                // Tablas de dietas cerradas (mismas columnas)
                $closedTabs = [
                    'cancelled' => ['diets' => $cancelledDiets, 'date' => 'Cancelada', 'tag' => 'tag-cancel', 'label' => 'Cancelada', 'empty' => 'No hay dietas canceladas.'],
                    'finished' => ['diets' => $finishedDiets, 'date' => 'Alta', 'tag' => 'tag-finished', 'label' => 'Finalizada', 'empty' => 'No hay dietas finalizadas. Usa el botón de alta en una dieta activa cuando el paciente salga de la institución.'],
                ];
            @endphp

            @if (session('success'))
                <div class="info-banner mb-4" role="status">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="danger-banner mb-4" role="alert">
                    No se guardó la dieta: {{ $errors->first() }}
                </div>
            @endif

            {{-- Despachos de cocina por confirmar recepción (se actualiza solo) --}}
            <livewire:dispatch-receipts />

            <div x-data="{ tab: @js($tab) }"
                x-init="$watch('tab', value => { const url = new URL(location.href); url.searchParams.set('tab', value); url.searchParams.delete('page'); history.replaceState(null, '', url); })">
                <div class="stats-grid">
                    <div class="stat-card green">
                        <div class="stat-label">Dietas activas</div>
                        <div class="stat-value">{{ $activeTotal }}</div>
                        <div class="stat-sub">Pacientes en seguimiento</div>
                    </div>
                    <div class="stat-card red">
                        <div class="stat-label">Canceladas</div>
                        <div class="stat-value">{{ $cancelledDiets->total() }}</div>
                        <div class="stat-sub">{{ $finishedDiets->total() }} finalizadas por alta</div>
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

                <div class="tabs-wrapper mb-4">
                    <button type="button" class="btn btn-ghost tab-btn" :class="tab === 'active' && 'active'"
                        :aria-pressed="tab === 'active'" @click="tab = 'active'">
                        Activas ({{ $activeTotal }})
                    </button>

                    <button type="button" class="btn btn-ghost tab-btn" :class="tab === 'cancelled' && 'active'"
                        :aria-pressed="tab === 'cancelled'" @click="tab = 'cancelled'">
                        Canceladas ({{ $cancelledDiets->total() }})
                    </button>

                    <button type="button" class="btn btn-ghost tab-btn" :class="tab === 'finished' && 'active'"
                        :aria-pressed="tab === 'finished'" @click="tab = 'finished'">
                        Finalizadas ({{ $finishedDiets->total() }})
                    </button>

                    <button class="btn btn-primary float-right" id="btnRegistrar" onclick="openCreateModal()">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"
                            viewBox="0 0 24 24">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Registrar Dieta
                    </button>

                    <div class="overlay" id="modalOverlay">
                        <x-modal-create-diets :specLabels="$specLabels" :consistencyLabels="$consistencyLabels"
                            :foodLabels="$foodLabels"></x-modal-create-diets>
                    </div>
                </div>


                <div class="card">
                    <div class="table-responsive">

                        <!-- TABLA ACTIVA -->
                        <div x-show="tab === 'active'" @unless ($tab === 'active') x-cloak @endunless>
                            {{-- Búsqueda en dietas activas (vuelve a la página 1) --}}
                            <form method="get" action="{{ route('dashboard.nutrition') }}" class="nd-search" role="search">
                                <input type="hidden" name="tab" value="active">
                                @if (request('per_page'))
                                    <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                                @endif
                                <input type="search" name="q" value="{{ $search }}" class="filter-input"
                                    placeholder="Buscar habitación o paciente" aria-label="Buscar dietas activas por habitación o paciente">
                                <button type="submit" class="btn btn-ghost btn-sm">Buscar</button>
                                @if ($search !== '')
                                    <a href="{{ route('dashboard.nutrition', array_filter(['tab' => 'active', 'per_page' => request('per_page')])) }}" class="btn btn-ghost btn-sm">Limpiar</a>
                                    <span class="nd-search-info">{{ $activeDiets->total() }} resultado(s) para «{{ $search }}»</span>
                                @endif
                                <x-meal-legend class="nd-legend" />
                            </form>
                            <table class="table-wrap">
                                <thead>
                                    <tr>
                                        <th>Hab.</th>
                                        <th>Paciente</th>
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
                                    @forelse ($activeDiets as $diet)
                                        <tr>
                                            <td>
                                                <span class="room-badge">{{ $diet->habitation }}</span>
                                            </td>

                                            <td>{{ $diet->name_patient }}</td>

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
                                                    <a href="{{ route('diets.history', ['dieta' => $diet->id]) }}"
                                                        class="btn btn-ghost btn-sm btn-icon" title="Ver historial"
                                                        aria-label="Ver historial de {{ $diet->name_patient }}">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/></svg>
                                                    </a>
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
                                                </div>
                                                <div class="overlay" id="modalEditOverlay{{ $diet->id }}">
                                                    <x-modal-edit-diets :diet="$diet" :specLabels="$specLabels"
                                                        :consistencyLabels="$consistencyLabels" :foodLabels="$foodLabels" />
                                                </div>
                                            </td>

                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="empty">{{ $search !== '' ? "No hay dietas activas que coincidan con «{$search}»." : 'No hay dietas activas.' }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                            {{ $activeDiets->onEachSide(1)->links('pagination.table') }}
                        </div>

                        <!-- TABLAS CANCELADAS / FINALIZADAS -->
                        @foreach ($closedTabs as $tabKey => $closed)
                            <div x-show="tab === '{{ $tabKey }}'" @unless ($tab === $tabKey) x-cloak @endunless>
                                <table class="table-wrap">
                                    <thead>
                                        <tr>
                                            <th>Hab.</th>
                                            <th>Paciente</th>
                                            <th>Tiempo</th>
                                            <th>Consistencia</th>
                                            <th>Observaciones</th>
                                            <th>{{ $closed['date'] }}</th>
                                            <th>Por</th>
                                            <th class="text-right">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($closed['diets'] as $diet)
                                            @php $version = $diet->currentVersion; @endphp
                                            <tr class="cancelled-row">
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
                                                        <span class="tag {{ $closed['tag'] }}">{{ $closed['label'] }}</span>
                                                        <a href="{{ route('diets.history', ['dieta' => $diet->id]) }}" class="btn btn-ghost btn-sm btn-icon"
                                                            title="Ver historial" aria-label="Ver historial de {{ $diet->name_patient }}">
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/></svg>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="empty">{{ $closed['empty'] }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                {{ $closed['diets']->onEachSide(1)->links('pagination.table') }}
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>

        </div>

    </div>
<style>
    /* Acciones de fila: en una sola línea, alineadas a la derecha, mismo alto */
    .row-actions { display: flex; justify-content: flex-end; align-items: center; gap: 6px; flex-wrap: nowrap; }
    .row-actions form { display: contents; }
    .row-actions .btn { white-space: nowrap; justify-content: center; }
    /* Botones solo icono: área táctil de 34px, el nombre va en title + aria-label */
    .btn-icon { width: 34px; height: 34px; padding: 0 !important; display: inline-flex; align-items: center; justify-content: center; }
    .btn-icon svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .btn-icon:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    .tag-finished { background: var(--blue-light); color: var(--blue); font-weight: 600; }
    .table-wrap td:last-child { width: 1%; white-space: nowrap; }
    /* Pestaña seleccionada */
    .tab-btn.active { background: var(--blue-light); color: var(--blue); border-color: #bfdbfe; font-weight: 600; }
    /* Búsqueda de dietas activas */
    .nd-search { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 12px 14px; border-bottom: 1px solid var(--border); }
    .nd-search .filter-input { max-width: 280px; }
    .nd-search-info { font-size: 12.5px; color: var(--text-muted); }
    .nd-legend { margin: 0 0 0 auto; }
    @media (max-width: 640px) { .nd-search .filter-input { max-width: none; flex: 1 1 100%; } .nd-legend { margin-left: 0; } }
</style>
<script>
    const SPEC_LABELS = @json($specLabels);
    const TIME_LABELS = @json($foodLabels);
    const CONS_LABELS = @json($consistencyLabels);
</script>

    <script src="{{ asset('assets/js/modal-render-chips.js') }}"></script>

</x-app-layout>
