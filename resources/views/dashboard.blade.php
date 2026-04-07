<style>

    :root{
        --bg:#f5f3ef; --surface:#fff; --surface2:#f0ede8; --border:#e2ddd6;
    --text:#1a1714; --text-muted:#7a736b;
    --accent:#2d6a4f; --accent-light:#d8f3dc;
    --accent2:#e63946; --accent2-light:#fde8ea;
    --pink:#e07a8f; --pink-light:#fce4ea;
    --blue:#4a7fcb; --blue-light:#ddeaf8;
    --amber:#d4892a; --amber-light:#fef3e2;
    --purple:#7b5ea7; --purple-light:#ede9f5;
    --shadow:0 1px 3px rgba(0,0,0,.07),0 4px 16px rgba(0,0,0,.05);
    --shadow-lg:0 8px 32px rgba(0,0,0,.14);
    --radius:12px; --radius-sm:8px;
    }

    .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:500;cursor:pointer;border:none;transition:all .2s;white-space:nowrap}
    .btn-primary{background:var(--accent);color:#fff}
    .btn-primary:hover{background:#245c43;transform:translateY(-1px);box-shadow:0 4px 12px rgba(45,106,79,.3)}
    .btn-danger{background:var(--accent2);color:#fff}
    .btn-danger:hover{background:#c0303b}
    .btn-ghost{background:transparent;color:var(--text-muted);border:1px solid var(--border)}
    .btn-ghost:hover{background:var(--surface2);color:var(--text)}
    .btn-info{background:var(--blue-light);color:var(--blue);border:1px solid #bfdbfe}
    .btn-info:hover{background:#c3d9f5}
    .btn-sm{padding:6px 11px;font-size:12.5px}
    .btn:disabled{opacity:.45;cursor:not-allowed;transform:none!important;box-shadow:none!important}

    .stats-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:18px}
    .stat-card{background:#fff;border:1px solid #ece8df;border-radius:14px;padding:16px 18px;box-shadow:var(--shadow);min-height:96px}
    .stat-card small{font-size:12px;color:#9c947f;text-transform:uppercase;letter-spacing:.35px}
    .stat-card strong{display:block;font-size:32px;color:#2f5f45;margin:8px 0 4px;font-weight:800}
    .stat-card span{font-size:13px;color:#7a736b}

    .filter-bar{background:#fff;border:1px solid #ece8df;border-radius:14px;padding:12px 14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;box-shadow:var(--shadow)}
    .filter-input{display:flex;align-items:center;gap:8px;border:1px solid #ddd8cc;border-radius:8px;padding:8px 12px;flex:1;min-width:220px}
    .filter-input input{border:none;outline:none;background:transparent;width:100%;font-size:14px;color:#444}
    .filter-group{display:flex;align-items:center;gap:8px;font-size:11px;color:#6f695f;text-transform:uppercase;font-weight:700}
    .filter-group label{font-size:11px;white-space:nowrap}
    .filter-group select,
    .filter-group input[type="date"]{border:1px solid #d6cfc3;border-radius:8px;padding:6px 8px;background:#faf7f2;font-size:13px;color:#423f3a}

    .tabs-wrapper{background:#fff;border:1px solid #ece8df;border-radius:14px;display:flex;gap:8px;padding:8px}
    .tabs-wrapper button{border:1px solid #d4cabd;border-radius:9px;padding:8px 14px;background:#f8f5ef;color:#746c61;font-weight:700;cursor:pointer;transition:.2s}
    .tabs-wrapper button.active{background:#fff;color:#2b5c41;border-color:#2b5c41;box-shadow:inset 0 0 0 1px #2b5c41}

    .table-wrap{overflow-x:auto}
    table{width:100%;border-collapse:collapse}
    thead tr{background:var(--surface2)}
    th{padding:10px 14px;text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);white-space:nowrap}
    td{padding:13px 14px;border-bottom:1px solid var(--border);vertical-align:middle;font-size:13.5px}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:var(--bg)}
    .cancelled-row td{background:#fff8f8}
    .cancelled-row:hover td{background:#fff2f2}

    /* ── TAGS / BADGES ── */
    .tags{display:flex;flex-wrap:wrap;gap:3px}
    .tag{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:500}
    .tag-tiempo{background:var(--blue-light);color:var(--blue)}
    .tag-consist{background:var(--accent-light);color:var(--accent)}
    .tag-espec{background:var(--purple-light);color:var(--purple)}
    .tag-cancel{background:var(--accent2-light);color:var(--accent2);font-weight:600}
    .badge{padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
    .badge-si{background:var(--accent-light);color:var(--accent)}
    .badge-no{background:var(--surface2);color:var(--text-muted)}
    .badge-cambios{background:var(--amber-light);color:var(--amber)}
    .badge-pendiente{background:#fef9c3;color:#92400e}
    .badge-entregada{background:var(--accent-light);color:var(--accent)}
    .badge-no-entregada{background:var(--accent2-light);color:var(--accent2)}
    .room-badge{background:var(--text);color:#fff;padding:3px 9px;border-radius:6px;font-family:'Syne',sans-serif;font-size:12px;font-weight:700}
    .time-badge{font-size:11px;background:var(--surface2);color:var(--text-muted);border-radius:6px;padding:2px 7px;font-weight:600}
    .user-badge{font-size:11px;display:inline-flex;align-items:center;gap:4px;background:var(--surface2);color:var(--text-muted);border-radius:6px;padding:2px 8px}

</style>
<x-app-layout>
    <x-slot name="header">
        {{-- <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2> --}}
    </x-slot>

    <div class="py-12">

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">

                @can('createDiets')

                @php
                    $activeDiets = $diets->where('status', 1);
                    $cancelledDiets = $diets->where('status', 0);
                    $isolatedCount = $diets->filter(fn($diet) => optional($diet->currentVersion)->isolation === 'Si')->count();
                    $changesCount = $diets->filter(fn($diet) => optional($diet->currentVersion)->changes && optional($diet->currentVersion)->changes !== '')->count();
                    $todayCount = $diets->filter(fn($diet) => optional($diet->created_at)->isToday())->count();
                @endphp

                <div x-data="{ tab: 'active', search: '', isolation: 'todos', consistency: 'todas', tiempo: 'todos', fecha: '{{ now()->format('Y-m-d') }}' }">
                    <div class="stats-grid mb-4">
                        <div class="stat-card">
                            <small>Dietas activas</small>
                            <strong>{{ $activeDiets->count() }}</strong>
                            <span>Pacientes en seguimiento</span>
                        </div>
                        <div class="stat-card">
                            <small>Canceladas</small>
                            <strong>{{ $cancelledDiets->count() }}</strong>
                            <span>Total histórico</span>
                        </div>
                        <div class="stat-card">
                            <small>En aislamiento</small>
                            <strong>{{ $isolatedCount }}</strong>
                            <span>Protocolo especial</span>
                        </div>
                        <div class="stat-card">
                            <small>Con cambios</small>
                            <strong>{{ $changesCount }}</strong>
                            <span>Dietas modificadas</span>
                        </div>
                        <div class="stat-card">
                            <small>Registradas hoy</small>
                            <strong>{{ $todayCount }}</strong>
                            <span>Nuevas del día</span>
                        </div>
                    </div>

                    <div class="filter-bar mb-4">
                        <div class="filter-input">
                            <span>🔍</span>
                            <input type="search" placeholder="Habitación o paciente..." x-model="search" />
                        </div>
                        <div class="filter-group">
                            <label>AISLAMIENTO</label>
                            <select x-model="isolation">
                                <option value="todos">Todos</option>
                                <option value="si">Sí</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>CONSISTENCIA</label>
                            <select x-model="consistency">
                                <option value="todas">Todas</option>
                                <option value="liquida">Líquida</option>
                                <option value="blanda">Blanda</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>TIEMPO</label>
                            <select x-model="tiempo">
                                <option value="todos">Todos</option>
                                <option value="desayuno">Desayuno</option>
                                <option value="almuerzo">Almuerzo</option>
                                <option value="cena">Cena</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>FECHA REGISTRO</label>
                            <input type="date" x-model="fecha" />
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" @click="search=''; isolation='todos'; consistency='todas'; tiempo='todos'; fecha='{{ now()->format('Y-m-d') }}';">Limpiar</button>
                    </div>

                    <div class="tabs-wrapper mb-4">
                        <button type="button" :class="tab==='active' ? 'active' : ''" @click="tab='active'">Activas</button>
                        <button type="button" :class="tab==='cancelled' ? 'active' : ''" @click="tab='cancelled'">Canceladas</button>
                        <button type="button" :class="tab==='all' ? 'active' : ''" @click="tab='all'">Todas</button>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
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
                                        <template x-if="tab==='active'">
                                           @foreach ($diets->where('status', '1') as $diet)
                                                <tr>
                                                    <td><span class="room-badge">{{ $diet->habitation }}</span></td>
                                                    <td>{{ $diet->name_patient }}</td>
                                                    <td>
                                                        @forelse ($diet->currentVersion->timeFood ?? [] as $item)
                                                            <span class="tag tag-tiempo">{{ ucfirst(str_replace('_', ' ', $item)) }}</span>
                                                        @empty
                                                            <span>—</span>
                                                        @endforelse
                                                    </td>
                                                    <td>
                                                        @forelse ($diet->currentVersion->consistency ?? [] as $item)
                                                            <span class="tag tag-consist">{{ ucfirst(str_replace('_', ' ', $item)) }}</span>
                                                        @empty
                                                            <span>—</span>
                                                        @endforelse
                                                    </td>
                                                    <td>
                                                        @forelse ($diet->currentVersion->specifications ?? [] as $item)
                                                            <span class="tag tag-espec">{{ ucfirst(str_replace('_', ' ', $item)) }}</span>
                                                        @empty
                                                            <span>—</span>
                                                        @endforelse
                                                    </td>
                                                    <td>{{ $diet->currentVersion->observations ?? '—' }}</td>
                                                    <td>{{ $diet->currentVersion->isolation ?? 'No' }}</td>
                                                    <td>{{ $diet->currentVersion->changes ?? 'N/A' }}</td>
                                                    <td class="text-right">
                                                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target=".edit-diet{{ $diet->id }}-modal-lg">Editar</button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </template>
                                        <template x-if="tab==='cancelled'">
                                            @foreach($cancelledDiets as $diet)
                                                <tr class="cancelled-row">
                                                    <td><span class="room-badge">{{ $diet->habitation }}</span></td>
                                                    <td>{{ $diet->name_patient }}</td>
                                                    <td>
                                                        @forelse ($diet->currentVersion->timeFood ?? [] as $item)
                                                            <span class="tag tag-tiempo">{{ ucfirst(str_replace('_', ' ', $item)) }}</span>
                                                        @empty
                                                            <span>—</span>
                                                        @endforelse
                                                    </td>
                                                    <td>
                                                        @forelse ($diet->currentVersion->consistency ?? [] as $item)
                                                            <span class="tag tag-consist">{{ ucfirst(str_replace('_', ' ', $item)) }}</span>
                                                        @empty
                                                            <span>—</span>
                                                        @endforelse
                                                    </td>
                                                    <td>
                                                        @forelse ($diet->currentVersion->specifications ?? [] as $item)
                                                            <span class="tag tag-espec">{{ ucfirst(str_replace('_', ' ', $item)) }}</span>
                                                        @empty
                                                            <span>—</span>
                                                        @endforelse
                                                    </td>
                                                    <td>{{ $diet->currentVersion->observations ?? '—' }}</td>
                                                    <td>{{ $diet->currentVersion->isolation ?? 'No' }}</td>
                                                    <td>{{ $diet->currentVersion->changes ?? 'N/A' }}</td>
                                                    <td class="text-right">
                                                        <button class="btn btn-ghost btn-sm" disabled>Cancelada</button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </template>
                                        <template x-if="tab==='all'">
                                            @foreach($diets as $diet)
                                                <tr>
                                                    <td><span class="room-badge">{{ $diet->habitation }}</span></td>
                                                    <td>{{ $diet->name_patient }}</td>
                                                    <td>
                                                        @forelse ($diet->currentVersion->timeFood ?? [] as $item)
                                                            <span class="tag tag-tiempo">{{ ucfirst(str_replace('_', ' ', $item)) }}</span>
                                                        @empty
                                                            <span>—</span>
                                                        @endforelse
                                                    </td>
                                                    <td>
                                                        @forelse ($diet->currentVersion->consistency ?? [] as $item)
                                                            <span class="tag tag-consist">{{ ucfirst(str_replace('_', ' ', $item)) }}</span>
                                                        @empty
                                                            <span>—</span>
                                                        @endforelse
                                                    </td>
                                                    <td>
                                                        @forelse ($diet->currentVersion->specifications ?? [] as $item)
                                                            <span class="tag tag-espec">{{ ucfirst(str_replace('_', ' ', $item)) }}</span>
                                                        @empty
                                                            <span>—</span>
                                                        @endforelse
                                                    </td>
                                                    <td>{{ $diet->currentVersion->observations ?? '—' }}</td>
                                                    <td>{{ $diet->currentVersion->isolation ?? 'No' }}</td>
                                                    <td>{{ $diet->currentVersion->changes ?? 'N/A' }}</td>
                                                    <td class="text-right">
                                                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target=".edit-diet{{ $diet->id }}-modal-lg">Editar</button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                            <table class="table-wrap">

                                <thead class="" style="">
                                    <tr>
                                        <th class=" text-xs">Hab.</th>
                                        <th class=" text-xs">Paciente</th>
                                        <th class=" text-xs">Tiempo</th>
                                        <th class=" text-xs">Consistencia</th>
                                        <th class=" text-xs">Especificaciones</th>
                                        <th class=" text-xs">Observaciones</th>
                                        <th class=" text-xs">Aislamiento</th>
                                        <th class=" text-xs">Cambios</th>
                                        <th class=" text-xs text-right">Acciones</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-200">
                                    @foreach ($diets->where('status', '1') as $diet)
                                    <tr>
                                        <td><span class="room-badge">{{ $diet->habitation }}</span></td>
                                        <td>{{ $diet->name_patient }}</td>
                                        <td>
                                            @forelse ($diet->currentVersion->timeFood ?? [] as $item)
                                            <small class="badge badge-light-info">
                                                {{ ucfirst(str_replace('_', ' ', $item)) }}
                                            </small>
                                            @empty
                                            <span class="text-gray-400">N/A</span>
                                            @endforelse
                                        </td>
                                        <td class="px-3 py-2 text-xs">
                                            @forelse ($diet->currentVersion->consistency ?? [] as $item)
                                            <small class="badge badge-light-info">
                                                {{ ucfirst(str_replace('_', ' ', $item)) }}
                                            </small>
                                            @empty
                                            <span class="text-gray-400">N/A</span>
                                            @endforelse
                                        </td>
                                        <td class="px-3 py-2 text-xs">
                                            @forelse ($diet->currentVersion->specifications ?? [] as $item)
                                            <small class="badge badge-light-primary">
                                                {{ ucfirst(str_replace('_', ' ', $item)) }}
                                            </small>
                                            @empty
                                            <span class="text-gray-400">N/A</span>
                                            @endforelse
                                        </td>
                                        <td class="px-3 py-2 text-xs">{{ $diet->currentVersion->observations ?? '' }}
                                        </td>
                                        <td class="px-3 py-2 text-xs">
                                            {{ $diet->currentVersion->isolation ?? '' }}
                                        </td>
                                        <td class="px-3 py-2 text-xs">{{ $diet->currentVersion->changes ?? '' }}</td>
                                        <td class="px-3 py-2 text-xs text-right">
                                            <button type="button" class="btn btn-xs btn-circle btn-outline-primary" data-toggle="modal"
                                                data-target=".edit-diet{{ $diet->id }}-modal-lg">
                                                <span class=" mdi mdi-square-edit-outline ">edit</span>
                                            </button>
                                            <div class="modal fade edit-diet{{ $diet->id }}-modal-lg  text-left"
                                                tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel"
                                                aria-hidden="true" style="display: none;">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h4 class="modal-title" id="myLargeModalLabel">Editar Dieta
                                                            </h4>
                                                            <button type="button" class="close" data-dismiss="modal"
                                                                aria-hidden="true">×</button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form
                                                                action="{{ route('dietas.update', ['diet' => $diet->id]) }}"
                                                                method="POST">
                                                                @csrf
                                                                @method('PUT')
                                                                <x-form-diet :diet="$diet" />
                                                            </form>

                                                        </div>
                                                    </div><!-- /.modal-content -->
                                                </div><!-- /.modal-dialog -->
                                            </div><!-- /.modal -->
                                            {{-- <button type="button" class="btn btn-ghost btn-sm" >Eliminar</button>
                                            --}}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>


                @endcan
                    </div>
                </div>
            </div>
        </div>


    </div>

    <!--  Modal content for the above example -->
    <div class="modal fade bs-example-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel"
        aria-hidden="true" style="display: none;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="myLargeModalLabel">Registrar Dieta</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('dietas.create') }}" method="POST">
                        @csrf
                        <x-form-diet :diet="null" />
                    </form>

                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

    <script src="{{ asset('assets/libs/jquery-nice-select/jquery.nice-select.min.js') }}"></script>
    <script src="{{ asset('assets/libs/switchery/switchery.min.js') }}"></script>
    <script src="{{ asset('assets/libs/select2/select2.min.js') }}"></script>
    <script src="{{ asset('assets/libs/bootstrap-select/bootstrap-select.min.js') }}"></script>
    <script src="{{ asset('assets/libs/bootstrap-touchspin/jquery.bootstrap-touchspin.min.js') }}"></script>
    <script src="{{ asset('assets/libs/bootstrap-maxlength/bootstrap-maxlength.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatables/dataTables.bootstrap4.js') }}"></script>
    <!-- Init js-->
    <script src="{{ asset('assets/js/pages/form-advanced.init.js') }}"></script>
    {{-- <script src="{{ asset('assets/libs/datatables/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatables/responsive.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatables/dataTables.buttons.min.js') }}"></script> --}}
    {{-- <script src="{{ asset('assets/libs/datatables/buttons.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatables/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatables/buttons.flash.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatables/buttons.print.min.js') }}"></script> --}}
    <script src="{{ asset('assets/js/pages/datatables.init.js') }}"></script>
    <!-- App js -->
    <script src="{{ asset('assets/js/app.min.js') }}"></script>
</x-app-layout>