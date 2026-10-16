<x-app-layout>
    <div class="content" id="page-reportes">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">

            @canany(['viewReports', 'generateFiles'])
                @php
                    $monthName = ucfirst($census['month']->locale('es')->isoFormat('MMMM YYYY'));
                    $maxMeal = max(max($census['totals']), 1);
                    $isolated = $snapshot['isolationCounts']['Sí'] ?? 0;
                    $activeNow = $snapshot['totalActiveDiets'];
                    // Paleta categórica validada (orden fijo, una por tiempo de comida)
                    $mealColors = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6'];
                    $chartDays = $census['days']->map(fn ($d) => $d['date']->format('d'))->values();
                    $chartSeries = collect(array_keys($mealLabels))
                        ->mapWithKeys(fn ($k) => [$k => $census['days']->pluck("meals.$k")->values()]);
                @endphp

                {{-- ===================== ENCABEZADO + FILTRO ===================== --}}
                <div class="flex items-center justify-between gap-4 mb-6" style="flex-wrap:wrap">
                    <div>
                        <h1 class="text-3xl font-semibold">Reporte Nutricional de Dietas</h1>
                        <p class="text-sm text-slate-500">
                            {{ $monthName }} · {{ $census['daysCounted'] }} día(s) contados
                        </p>
                    </div>

                    <form method="GET" action="{{ url()->current() }}" class="flex items-center gap-4">
                        <label for="mes" class="text-sm text-slate-500">Mes</label>
                        <input type="month" id="mes" name="mes" value="{{ $selectedMonth }}"
                            max="{{ now()->format('Y-m') }}" onchange="this.form.submit()"
                            class="rounded-2xl border border-slate-200 text-sm">
                        <a href="{{ route('reports.monthly.excel', ['mes' => $selectedMonth]) }}" class="btn btn-success">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                                <polyline points="7 10 12 15 17 10" />
                                <line x1="12" y1="15" x2="12" y2="3" />
                            </svg>
                            Descargar Excel del mes
                        </a>
                    </form>
                </div>
                @error('mes')
                    <div class="danger-banner mb-4">{{ $message }}</div>
                @enderror

                {{-- ===================== KPIs ===================== --}}
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Bandejas del mes</div>
                        <div class="stat-value">{{ number_format($census['totalTrays'], 0, ',', '.') }}</div>
                        <div class="stat-sub">Suma de todos los tiempos de comida</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Promedio diario</div>
                        <div class="stat-value">{{ number_format($census['avgDailyDiets'], 1, ',', '.') }}</div>
                        <div class="stat-sub">Dietas vigentes por día</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Pacientes en el mes</div>
                        <div class="stat-value">{{ $census['perDiet']->count() }}</div>
                        <div class="stat-sub">Con al menos un día de dieta</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Dietas activas hoy</div>
                        <div class="stat-value">{{ $activeNow }}</div>
                        <div class="stat-sub">{{ $isolated }} en aislamiento</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
                    {{-- ===================== PRODUCCIÓN DIARIA ===================== --}}
                    <div class="xl:col-span-7 bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                        <div class="mb-6">
                            <h2 class="text-lg font-semibold">Producción diaria</h2>
                            <p class="text-sm text-slate-500">Bandejas por día y tiempo de comida · {{ $monthName }}</p>
                        </div>
                        <div class="h-[360px]">
                            <canvas id="dailyChart" class="h-full" role="img"
                                aria-label="Barras apiladas de bandejas por día y tiempo de comida"></canvas>
                        </div>

                        <details class="mt-6">
                            <summary class="text-sm font-medium text-slate-700" style="cursor:pointer">Ver tabla diaria</summary>
                            <div class="overflow-x-auto mt-4">
                                <table class="min-w-full text-left border-separate border-spacing-0 text-sm">
                                    <thead class="bg-slate-50 text-slate-700 uppercase tracking-wide">
                                        <tr>
                                            <th class="px-4 py-3">Día</th>
                                            <th class="px-4 py-3 text-center">Dietas</th>
                                            @foreach ($mealLabels as $label)
                                                <th class="px-4 py-3 text-center">{{ $label }}</th>
                                            @endforeach
                                            <th class="px-4 py-3 text-center">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($census['days'] as $day)
                                            <tr class="border-t border-slate-200 text-slate-600">
                                                <td class="px-4 py-3">{{ $day['date']->format('d/m') }}</td>
                                                <td class="px-4 py-3 text-center">{{ $day['diets'] }}</td>
                                                @foreach ($day['meals'] as $count)
                                                    <td class="px-4 py-3 text-center">{{ $count }}</td>
                                                @endforeach
                                                <td class="px-4 py-3 text-center font-medium text-slate-900">{{ $day['total'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </div>

                    {{-- ===================== TOTAL DEL MES POR TIEMPO ===================== --}}
                    <div class="xl:col-span-5 bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                        <div class="mb-6">
                            <h2 class="text-lg font-semibold">Total del mes por tiempo de comida</h2>
                            <p class="text-sm text-slate-500">{{ $monthName }}</p>
                        </div>

                        @foreach ($mealLabels as $key => $label)
                            @php $count = $census['totals'][$key]; @endphp
                            <div class="bar-horiz">
                                <div class="bar-horiz-label">{{ $label }}</div>
                                <div class="bar-horiz-track">
                                    <div class="bar-horiz-fill"
                                        style="width: {{ round(($count / $maxMeal) * 100) }}%; background: {{ $mealColors[$loop->index] }}">
                                    </div>
                                </div>
                                <div class="bar-horiz-count" style="width:auto;min-width:72px">
                                    {{ number_format($count, 0, ',', '.') }}
                                    <span class="text-xs text-slate-500">
                                        ({{ $census['totalTrays'] ? round(($count / $census['totalTrays']) * 100) : 0 }}%)
                                    </span>
                                </div>
                            </div>
                        @endforeach

                        <div class="mt-6 flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                            <div class="text-sm font-medium text-slate-700">Total bandejas</div>
                            <div class="text-base font-semibold text-slate-900">
                                {{ number_format($census['totalTrays'], 0, ',', '.') }}
                            </div>
                        </div>
                        <p class="mt-4 text-xs text-slate-500">
                            Cada dieta se cuenta con su última versión registrada hasta el cierre del mes: sus tiempos de
                            comida se multiplican por los días en que estuvo activa (desde su creación hasta su cancelación).
                        </p>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- ===================== ÚLTIMOS 6 MESES ===================== --}}
                    <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                        <div class="mb-4">
                            <h2 class="text-lg font-semibold">Totales por mes</h2>
                            <p class="text-sm text-slate-500">Últimos 6 meses hasta {{ $monthName }}</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left border-separate border-spacing-0">
                                <thead class="bg-slate-50 text-slate-700 text-sm uppercase tracking-wide">
                                    <tr>
                                        <th class="px-4 py-3">Mes</th>
                                        @foreach ($mealLabels as $label)
                                            <th class="px-4 py-3 text-center">{{ $label }}</th>
                                        @endforeach
                                        <th class="px-4 py-3 text-center">Total</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($history as $row)
                                        <tr class="border-t border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
                                            <td class="px-4 py-3 font-medium text-slate-900">
                                                <a href="{{ url()->current() }}?mes={{ $row['month']->format('Y-m') }}">
                                                    {{ ucfirst($row['month']->locale('es')->isoFormat('MMM YYYY')) }}
                                                </a>
                                            </td>
                                            @foreach ($row['totals'] as $count)
                                                <td class="px-4 py-3 text-center">{{ $count }}</td>
                                            @endforeach
                                            <td class="px-4 py-3 text-center font-semibold text-slate-900">{{ $row['totalTrays'] }}</td>
                                            <td class="px-4 py-3 text-right">
                                                @if ($row['totalTrays'] > 0)
                                                    <a href="{{ route('reports.monthly.excel', ['mes' => $row['month']->format('Y-m')]) }}"
                                                        class="btn btn-ghost btn-sm" title="Descargar Excel">Excel</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-6">
                        {{-- ===================== TOP ESPECIFICACIONES (HOY) ===================== --}}
                        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                            <div class="mb-5">
                                <h3 class="text-lg font-semibold">Top especificaciones</h3>
                                <p class="text-sm text-slate-500">Dietas activas hoy</p>
                            </div>
                            @if ($snapshot['topSpecifications']->isEmpty())
                                <div class="text-sm text-slate-500">No hay especificaciones registradas.</div>
                            @else
                                @php $maxSpec = max($snapshot['topSpecifications']->first(), 1); @endphp
                                <div class="space-y-4">
                                    @foreach ($snapshot['topSpecifications'] as $spec => $count)
                                        <div>
                                            <div class="flex items-center justify-between text-sm font-medium text-slate-700">
                                                <div>{{ $specLabels[$spec] ?? \App\Services\Reports\DietReportService::label('specifications', $spec) }}</div>
                                                <div>{{ $count }}</div>
                                            </div>
                                            <div class="mt-2 h-2 rounded-full bg-slate-200 overflow-hidden">
                                                <div class="h-full rounded-full {{ $specColors[$spec] ?? 'bg-gray-400' }}"
                                                    style="width: {{ round(($count / $maxSpec) * 100) }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- ===================== AISLAMIENTO (HOY) ===================== --}}
                        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                            <div class="mb-5">
                                <h3 class="text-lg font-semibold">Aislamiento</h3>
                                <p class="text-sm text-slate-500">Dietas activas hoy</p>
                            </div>
                            <div class="flex items-end gap-4">
                                <div class="text-4xl font-bold text-slate-900">
                                    {{ $activeNow ? round(($isolated / $activeNow) * 100) : 0 }}%
                                </div>
                                <div class="text-sm text-slate-500">con aislamiento</div>
                            </div>
                            <div class="mt-6 space-y-3">
                                <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                                    <div class="text-sm font-medium text-slate-700">Aislamiento</div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $isolated }}</div>
                                </div>
                                <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                                    <div class="text-sm font-medium text-slate-700">Sin aislamiento</div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $activeNow - $isolated }}</div>
                                </div>
                                <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                                    <div class="text-sm font-medium text-slate-700">Dietas-día en aislamiento (mes)</div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $census['isolationDays'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    (function () {
                        const days = @json($chartDays);
                        const meals = @json($mealLabels);
                        const colors = @json($mealColors);
                        const series = @json($chartSeries);

                        new Chart(document.getElementById('dailyChart'), {
                            type: 'bar',
                            data: {
                                labels: days,
                                datasets: Object.keys(meals).map((key, i) => ({
                                    label: meals[key],
                                    data: series[key],
                                    backgroundColor: colors[i],
                                    borderColor: '#ffffff',          // separación de 2px entre segmentos
                                    borderWidth: { top: 2 },
                                    borderSkipped: false,
                                    maxBarThickness: 22,
                                })),
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: { mode: 'index', intersect: false },
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: { usePointStyle: true, pointStyle: 'circle', color: '#52514e', boxWidth: 8 },
                                    },
                                    tooltip: {
                                        filter: (item) => item.raw > 0,
                                        callbacks: {
                                            title: (items) => `Día ${items[0].label}`,
                                            footer: (items) => `Total: ${items.reduce((s, i) => s + i.raw, 0)} bandejas`,
                                        },
                                    },
                                },
                                scales: {
                                    x: { stacked: true, grid: { display: false }, ticks: { color: '#6b7280' } },
                                    y: {
                                        stacked: true,
                                        beginAtZero: true,
                                        grid: { color: '#eef0f3' },
                                        border: { display: false },
                                        ticks: { color: '#6b7280', precision: 0 },
                                    },
                                },
                            },
                        });
                    })();
                </script>
            @endcanany
        </div>
    </div>
</x-app-layout>
