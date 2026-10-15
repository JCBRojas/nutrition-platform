<x-app-layout>
<div class="content" id="page-reportes">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">

                @can('createDiets')

                    <h1 class="text-3xl font-semibold text-center">
                        Reporte Nutricional de Dietas
                    </h1>

                    <div class="mt-8 grid grid-cols-1 xl:grid-cols-12 gap-6">
                        <div class="xl:col-span-5 bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <h2 class="text-lg font-semibold">Distribución por tiempo</h2>
                                    <p class="text-sm text-slate-500">Dietas activas por tipo de comida</p>
                                </div>
                            </div>

                            <div class="relative h-[320px]">
                                <canvas id="timeDistributionChart" class="h-full"></canvas>
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    <span class="text-sm uppercase tracking-[0.2em] text-slate-400">Dietas</span>
                                    <span class="text-4xl font-bold text-slate-900">{{ $totalActiveDiets }}</span>
                                </div>
                            </div>

                            <div class="mt-6 space-y-3">
                                @php
                                    $distributionItems = [
                                        [
                                            'label' => 'Desayuno',
                                            'value' => $globalTotals->desayuno ?? 0,
                                            'color' => 'bg-blue-500',
                                        ],
                                        [
                                            'label' => 'Almuerzo',
                                            'value' => $globalTotals->almuerzo ?? 0,
                                            'color' => 'bg-rose-500',
                                        ],
                                        [
                                            'label' => 'Cena',
                                            'value' => $globalTotals->cena ?? 0,
                                            'color' => 'bg-amber-500',
                                        ],
                                        [
                                            'label' => 'Fraccionada',
                                            'value' => $globalTotals->fraccionada ?? 0,
                                            'color' => 'bg-violet-500',
                                        ],
                                    ];

                                    $totalItems = max($totalActiveDiets, 1);
                                @endphp

                                @foreach ($distributionItems as $item)
                                    <div class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 p-3">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-flex h-3.5 w-3.5 rounded-full {{ $item['color'] }}"></span>
                                            <span class="text-sm font-medium text-slate-700">{{ $item['label'] }}</span>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-base font-semibold text-slate-900">{{ $item['value'] }}</div>
                                            <div class="text-xs text-slate-500">
                                                {{ round(($item['value'] / $totalItems) * 100) }}%</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="xl:col-span-7 bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <h2 class="text-lg font-semibold">Producción mensual</h2>
                                    <p class="text-sm text-slate-500">Conteo de dietas por mes y tipo de comida</p>
                                </div>
                            </div>
                            <div class="h-[360px]">
                                <canvas id="nutritionChart" class="h-full"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                            <div class="mb-4 flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold">Totales por mes</h2>
                                    <p class="text-sm text-slate-500">Resumen mensual por tipo de dieta</p>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-left border-separate border-spacing-0">
                                    <thead class="bg-slate-50 text-slate-700 text-sm uppercase tracking-wide">
                                        <tr>
                                            <th class="px-4 py-3">Mes</th>
                                            <th class="px-4 py-3 text-center">Desayuno</th>
                                            <th class="px-4 py-3 text-center">Almuerzo</th>
                                            <th class="px-4 py-3 text-center">Cena</th>
                                            <th class="px-4 py-3 text-center">Fraccionada</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($monthlyTotals as $row)
                                            <tr class="border-t border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
                                                <td class="px-4 py-3 font-medium text-slate-900">{{ $row->mes }}</td>
                                                <td class="px-4 py-3 text-center">{{ $row->desayuno }}</td>
                                                <td class="px-4 py-3 text-center">{{ $row->almuerzo }}</td>
                                                <td class="px-4 py-3 text-center">{{ $row->cena }}</td>
                                                <td class="px-4 py-3 text-center">{{ $row->fraccionada }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-6 text-center text-slate-500">No hay datos
                                                    mensuales</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>


                        <div class="space-y-6">
                            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                                <div class="mb-5">
                                    <h3 class="text-lg font-semibold">Top especificaciones</h3>
                                    <p class="text-sm text-slate-500">Las especificaciones más frecuentes</p>
                                </div>
                                @if ($topSpecifications->isEmpty())
                                    <div class="text-sm text-slate-500">No hay especificaciones registradas.</div>
                                @else
                                    @php
                                    $maxSpec = $topSpecifications->first() ?? 1;
                                         

                                            
                                        @endphp 
                                    <div class="space-y-4">
                                        @foreach ($topSpecifications as $spec => $count)
                                        @php
                                            // Color por defecto si la especificación no está en el mapa
                                            $color = $specColors[$spec] ?? 'bg-gray-400';
                                        @endphp
                                            <div>
                                                <div
                                                    class="flex items-center justify-between text-sm font-medium text-slate-700">
                                                    <div>{{ $labelsColors[$spec] }}</div>
                                                    <div>{{ $count }}</div>
                                                </div>
                                                <div class="mt-2 h-2 rounded-full bg-slate-200 overflow-hidden">
                                                    <div class="h-full rounded-full {{$color}}"
                                                        style="width: {{ $maxSpec ? round(($count / $maxSpec) * 100) : 0 }}%">
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                                <div class="mb-5">
                                    <h3 class="text-lg font-semibold">Aislamiento</h3>
                                    <p class="text-sm text-slate-500">Pacientes en protocolo de aislamiento</p>
                                </div>
                                @php
                                    $isolated = $isolationCounts['Sí'] ?? 0;
                                    $notIsolated = $isolationCounts['No'] ?? 0;
                                    $totalIsolation = max($isolated + $notIsolated, 1);
                                    $isolatedPercent = round(($isolated / $totalIsolation) * 100);
                                @endphp
                                <div class="flex items-end gap-4">
                                    <div class="text-4xl font-bold text-slate-900">{{ $isolatedPercent }}%</div>
                                    <div class="text-sm text-slate-500">de dietas activas con aislamiento</div>
                                </div>
                                <div class="mt-6 space-y-3">
                                    <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                                        <div class="text-sm font-medium text-slate-700">Aislamiento</div>
                                        <div class="text-sm font-semibold text-slate-900">{{ $isolated }}</div>
                                    </div>
                                    <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3">
                                        <div class="text-sm font-medium text-slate-700">Sin aislamiento</div>
                                        <div class="text-sm font-semibold text-slate-900">{{ $notIsolated }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        const labels = @json($chartLabels);
                        const desayuno = @json($chartBreakfast);
                        const almuerzo = @json($chartLunch);
                        const cena = @json($chartDinner);

                        new Chart(document.getElementById('nutritionChart'), {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                        label: 'Desayuno',
                                        data: desayuno,
                                        backgroundColor: '#2563eb',
                                        borderRadius: 12,
                                        barThickness: 24,
                                    },
                                    {
                                        label: 'Almuerzo',
                                        data: almuerzo,
                                        backgroundColor: '#ec4899',
                                        borderRadius: 12,
                                        barThickness: 24,
                                    },
                                    {
                                        label: 'Cena',
                                        data: cena,
                                        backgroundColor: '#f59e0b',
                                        borderRadius: 12,
                                        barThickness: 24,
                                    },
                                ],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                        labels: {
                                            usePointStyle: true,
                                            pointStyle: 'circle',
                                        },
                                    },
                                },
                                scales: {
                                    x: {
                                        grid: {
                                            display: false
                                        },
                                        ticks: {
                                            color: '#6b7280'
                                        },
                                    },
                                    y: {
                                        beginAtZero: true,
                                        grid: {
                                            color: '#e5e7eb'
                                        },
                                        ticks: {
                                            color: '#6b7280',
                                            precision: 0
                                        },
                                    },
                                },
                            },
                        });

                        new Chart(document.getElementById('timeDistributionChart'), {
                            type: 'doughnut',
                            data: {
                                labels: ['Desayuno', 'Almuerzo', 'Cena', 'Fraccionada'],
                                datasets: [{
                                    data: [{{ $globalTotals->desayuno ?? 0 }}, {{ $globalTotals->almuerzo ?? 0 }},
                                        {{ $globalTotals->cena ?? 0 }}, {{ $globalTotals->fraccionada ?? 0 }}
                                    ],
                                    backgroundColor: ['#2563eb', '#ec4899', '#f59e0b', '#8b5cf6'],
                                    hoverOffset: 8,
                                }],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '70%',
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                },
                            },
                        });
                    </script>

                @endcan
            </div>



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
