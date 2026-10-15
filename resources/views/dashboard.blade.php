<x-app-layout>

    <div class="content" id="page-reportes">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">

            @php
                $activeDiets = $diets->where('status', 1);
                $cancelledDiets = $diets->where('status', 0);
                $isolatedCount = $diets
                    ->filter(fn($diet) => optional($diet->currentVersion)->isolation === 'Sí')
                    ->count();
                $changesCount = $diets
                    ->filter(
                        fn($diet) => optional($diet->currentVersion)->changes &&
                            optional($diet->currentVersion)->changes !== '',
                    )
                    ->count();
                $todayCount = $diets->filter(fn($diet) => optional($diet->created_at)->isToday())->count();
            @endphp

            <div x-data="{ tab: 'active' }">
                <div class="stats-grid">
                    <div class="stat-card green">
                        <div class="stat-label">Dietas activas</div>
                        <div class="stat-value">{{ $activeDiets->count() }}</div>
                        <div class="stat-sub">Pacientes en seguimiento</div>
                    </div>
                    <div class="stat-card red">
                        <div class="stat-label">Canceladas</div>
                        <div class="stat-value">{{ $cancelledDiets->count() }}</div>
                        <div class="stat-sub">Total histórico</div>
                    </div>
                    <div class="stat-card amber">
                        <div class="stat-label">En aislamiento</div>
                        <div class="stat-value">{{ $isolatedCount }}</div>
                        <div class="stat-sub">Protocolo especial</div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-label">Con cambios</div>
                        <div class="stat-value">{{ $changesCount }}</div>
                        <div class="stat-sub">Dietas modificadas</div>
                    </div>
                </div>

                <div class="tabs-wrapper mb-4">
                    <button type="button" class="btn btn-info" :class="tab === 'active' ? 'active' : ''"
                        @click="tab = 'active'">
                        Activas
                    </button>

                    <button type="button" class="btn btn-ghost" :class="tab === 'cancelled' ? 'active' : ''"
                        @click="tab = 'cancelled'">
                        Canceladas
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
                        <div x-show="tab === 'active'">
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
                                    @foreach ($activeDiets as $diet)
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
                                                    <span class="tag tag-tiempo">
                                                        {{ $foodLabels[$item] ?? '' }}
                                                    </span>
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
                                            <td>{{ $diet->currentVersion->changes ?? 'N/A' }}</td>

                                            <td class="">
                                                <button class="btn btn-success btn-sm"
                                                    onclick='openEditModal({{ $diet->id }}, @json($diet))'>
                                                    Editar
                                                </button>
                                                <div class="overlay" id="modalEditOverlay{{ $diet->id }}">
                                                    <x-modal-edit-diets :diet="$diet" :specLabels="$specLabels"
                                                        :consistencyLabels="$consistencyLabels" :foodLabels="$foodLabels" />
                                                </div>

                                                <form method="post" action="{{ route('diets.canceled', $diet->id)}}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-danger btn-sm"
                                                        onclick="return confirm('¿Estás seguro de que deseas cancelar esta dieta?')">
                                                        Cancelar
                                                    </button>
                                                </form>
                                            </td>

                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- TABLA CANCELADAS -->
                        <div x-show="tab === 'cancelled'" x-cloak>
                            <table class="table-wrap">
                                <thead>
                                    <tr>
                                        <th>Hab.</th>
                                        <th>Paciente</th>
                                        <th>Tiempo</th>
                                        <th>Consistencia</th>
                                        <th>Observaciones</th>
                                        <th>Cancelada</th>
                                        <th>Por</th>
                                        <th class="text-right">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($cancelledDiets as $diet)
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
                                            <td class="text-right"><span class="tag tag-cancel">Cancelada</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="empty">No hay dietas canceladas</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
<script>
    const SPEC_LABELS = @json($specLabels);
    const TIME_LABELS = @json($foodLabels);
    const CONS_LABELS = @json($consistencyLabels);
</script>

    <script src="{{ asset('assets/js/modal-render-chips.js') }}"></script>

</x-app-layout>
