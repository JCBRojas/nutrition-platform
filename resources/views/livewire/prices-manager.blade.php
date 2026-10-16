@php
    $cop = fn ($v) => $v === null ? '—' : '$ '.number_format($v, 0, ',', '.');
    $svc = fn ($key) => ucfirst(mb_strtolower($services[$key]));
@endphp
<div class="content" id="page-precios">
    <style>
        .pm-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 400px); gap: 20px; align-items: start; }
        .pm-money { font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; }
        .pm-type td { background: var(--bg); font-weight: 700; padding-top: 14px; }
        .pm-row td:first-child { padding-left: 28px; }
        .pm-edit td { background: var(--surface2); }
        .pm-edit .pm-fields { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
        .pm-edit .form-group { margin: 0; }
        .pm-hist td { font-size: 12px; color: var(--text-muted); background: var(--bg); }
        .pm-hist td:first-child { padding-left: 44px; }
        .pm-total td { font-weight: 700; border-top: 2px solid var(--border); }
        .pm-missing { color: var(--amber); font-weight: 600; }
        .pm-side { display: flex; flex-direction: column; gap: 20px; position: sticky; top: 16px; }
        .pm-warn { border-left: 3px solid var(--amber); background: var(--amber-light); padding: 10px 14px; font-size: 13px; border-radius: 0 8px 8px 0; }
        .pm-warn ul { margin: 6px 0 0; padding-left: 18px; }
        @media (max-width: 1100px) { .pm-grid { grid-template-columns: minmax(0, 1fr); } .pm-side { position: static; } }
    </style>

    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
        <h1 class="card-title mb-4">Precios y facturación</h1>

        @if (session('success'))
            <div class="info-banner mb-4" role="status">{{ session('success') }}</div>
        @endif

        <div class="pm-grid">
            {{-- ===================== PRECIOS TIPO × SERVICIO ===================== --}}
            <div class="card">
                <div class="filters-bar" style="margin:0;padding:12px 18px;justify-content:space-between">
                    <div>
                        <div class="card-title">Precios vigentes hoy</div>
                        <div class="text-sm text-slate-500">
                            Valor por bandeja (COP).
                            @if ($missingCount)
                                <span class="pm-missing">{{ $missingCount }} de {{ count(\App\Services\BillingService::rows()) }} sin precio.</span>
                            @endif
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        <select class="filter-select" wire:model.live="typeFilter" aria-label="Tipo de dieta">
                            <option value="">Todos los tipos</option>
                            @foreach ($types as $key => $t)
                                <option value="{{ $key }}">{{ $t['name'] }}</option>
                            @endforeach
                        </select>
                        <label style="display:inline-flex;gap:6px;align-items:center;font-size:13px">
                            <input type="checkbox" wire:model.live="onlyMissing"> Solo sin precio
                        </label>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="vtable">
                        <thead>
                            <tr>
                                <th>Tipo / servicio</th>
                                <th style="text-align:right">Precio</th>
                                <th>Vigente desde</th>
                                <th style="text-align:right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $type => $typeRows)
                                <tr class="pm-type" wire:key="type-{{ $type }}">
                                    <td colspan="4">{{ $types[$type]['name'] }}</td>
                                </tr>
                                @foreach ($typeRows as $r)
                                    @php
                                        $key = "{$r['type']}.{$r['service']}";
                                        $p = $current->get($key);
                                    @endphp
                                    <tr class="pm-row" wire:key="row-{{ $key }}">
                                        <td>{{ $svc($r['service']) }}</td>
                                        <td class="pm-money">
                                            @if ($p) {{ $cop((float) $p->price) }} @else <span class="pm-missing">Sin precio</span> @endif
                                        </td>
                                        <td>{{ $p?->valid_from->format('d/m/Y') ?? '—' }}</td>
                                        <td style="text-align:right;white-space:nowrap">
                                            <button type="button" class="btn btn-ghost btn-sm" wire:click="toggleHistory('{{ $key }}')"
                                                aria-expanded="{{ $openHistory === $key ? 'true' : 'false' }}">Historial</button>
                                            <button type="button" class="btn btn-primary btn-sm" wire:click="edit('{{ $r['type'] }}', '{{ $r['service'] }}')">
                                                {{ $p ? 'Cambiar' : 'Asignar' }}
                                            </button>
                                        </td>
                                    </tr>

                                    @if ($editType === $r['type'] && $editService === $r['service'])
                                        <tr class="pm-edit" wire:key="edit-{{ $key }}">
                                            <td colspan="4">
                                                <form wire:submit="save" class="pm-fields">
                                                    <div class="form-group">
                                                        <label for="price">Nuevo precio (COP)</label>
                                                        <input id="price" type="text" inputmode="decimal" wire:model="price" placeholder="15000" autofocus>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="validFrom">Vigente desde</label>
                                                        <input id="validFrom" type="date" wire:model="validFrom">
                                                    </div>
                                                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Guardar precio</button>
                                                    <button type="button" class="btn btn-ghost" wire:click="cancel">Cancelar</button>
                                                </form>
                                                @error('price') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                                                @error('validFrom') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                                                @error('editService') <div class="form-hint text-red-600">{{ $message }}</div> @enderror
                                                <div class="form-hint">Los días anteriores a esa fecha conservan su precio. Si ya hay un precio con la misma fecha, se reemplaza.</div>
                                            </td>
                                        </tr>
                                    @endif

                                    @if ($openHistory === $key)
                                        @forelse ($history as $h)
                                            <tr class="pm-hist" wire:key="hist-{{ $h->id }}">
                                                <td>Desde {{ $h->valid_from->format('d/m/Y') }}</td>
                                                <td class="pm-money">{{ $cop((float) $h->price) }}</td>
                                                <td colspan="2">{{ $h->createdBy?->name ?? '—' }}, {{ $h->created_at->format('d/m/Y h:i a') }}</td>
                                            </tr>
                                        @empty
                                            <tr class="pm-hist"><td colspan="4">Sin precios registrados.</td></tr>
                                        @endforelse
                                    @endif
                                @endforeach
                            @empty
                                <tr><td colspan="4" class="empty">Todas las filas tienen precio.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ===================== FACTURACIÓN DEL MES ===================== --}}
            <div class="pm-side">
                <div class="card">
                    <div class="filters-bar" style="margin:0;padding:12px 18px;justify-content:space-between">
                        <div>
                            <div class="card-title">Facturación del mes</div>
                            <div class="text-sm text-slate-500">{{ $monthName }}, {{ $monthly['daysCounted'] }} día(s)</div>
                        </div>
                        <input type="month" wire:model.live="month" max="{{ now()->format('Y-m') }}"
                            class="filter-input" style="width:auto" aria-label="Mes">
                    </div>
                    <table class="vtable">
                        <thead>
                            <tr><th>Servicio</th><th style="text-align:right">Cantidad</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($monthly['serviceTotals'] as $service => $n)
                                <tr><td>{{ $svc($service) }}</td><td class="pm-money">{{ number_format($n, 0, ',', '.') }}</td></tr>
                            @endforeach
                            <tr class="pm-total"><td>Total servicios</td><td class="pm-money">{{ number_format($monthly['totalTrays'], 0, ',', '.') }}</td></tr>
                            <tr class="pm-total"><td>Valor del mes</td><td class="pm-money">{{ $cop($monthly['total']) }}</td></tr>
                        </tbody>
                    </table>
                    <div style="padding:14px 18px">
                        <a href="{{ route('billing.excel', ['mes' => $monthValue]) }}" class="btn btn-success" style="width:100%;justify-content:center">
                            Descargar facturación en Excel
                        </a>
                    </div>
                </div>

                @if ($monthly['unassigned']->isNotEmpty())
                    <div class="pm-warn" role="alert">
                        <strong>{{ $monthly['unassigned']->count() }} dieta(s) sin tipo de facturación.</strong>
                        No se incluyen en el Excel. El nutricionista debe editarlas y elegir el tipo de dieta.
                        <ul>
                            @foreach ($monthly['unassigned']->take(8) as $u)
                                <li>Hab. {{ $u['diet']->habitation }}, {{ $u['diet']->name_patient }} ({{ $u['days'] }} días)</li>
                            @endforeach
                            @if ($monthly['unassigned']->count() > 8)
                                <li>y {{ $monthly['unassigned']->count() - 8 }} más</li>
                            @endif
                        </ul>
                    </div>
                @endif

                @if ($monthly['notReceived'] > 0)
                    <div class="pm-warn" role="alert">
                        <strong>{{ $monthly['notReceived'] }} servicio(s) sin despacho recibido en {{ $monthName }}.</strong>
                        No se facturan: solo cuentan los tiempos que cocina despachó y el nutricionista confirmó como recibidos.
                    </div>
                @endif

                @if (count($monthly['missingPrices']))
                    <div class="pm-warn" role="alert">
                        <strong>{{ count($monthly['missingPrices']) }} fila(s) con servicios sin precio en {{ $monthName }}.</strong>
                        Se facturan en $ 0 hasta que tengan precio vigente.
                        <ul>
                            @foreach (array_slice($monthly['missingPrices'], 0, 8) as $key)
                                @php [$t, $s] = explode('.', $key); @endphp
                                <li>{{ $types[$t]['name'] }} / {{ $svc($s) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
