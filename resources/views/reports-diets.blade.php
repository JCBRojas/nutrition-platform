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
                    {{-- ===============================
                PRODUCCION COCINA HOY
                =============================== --}}

                    {{-- PRODUCCIÓN COCINA HOY --}}

                    <h4>Producción de cocina hoy</h4>

                    <table class="table table-bordered">

                        <thead>

                            <tr>
                                {{-- <th>Tipo dieta</th> --}}
                                <th>Desayuno</th>
                                <th>Media mañana</th>
                                <th>Almuerzo</th>
                                <th>Algo</th>
                                <th>Merienda</th>
                                <th>Cena</th>
                            </tr>

                        </thead>

                        <tbody>

                            @foreach ($kitchenToday as $row)
                                <tr>

                                    {{-- <td>{{ $row->tipo_dieta }}</td> --}}

                                    <td>{{ $row->desayuno }}</td>
                                    <td>{{ $row->media_manana }}</td>
                                    <td>{{ $row->almuerzo }}</td>
                                    <td>{{ $row->algo }}</td>
                                    <td>{{ $row->merienda }}</td>
                                    <td>{{ $row->cena }}</td>

                                </tr>
                            @endforeach

                        </tbody>

                    </table>



                    {{-- TOTALES GLOBALES --}}

                    <h4 class="mt-5">Totales globales por tipo de dieta</h4>

                    <table class="table table-bordered">

                        <thead>

                            <tr>
                                {{-- <th>Tipo dieta</th> --}}
                                <th>Desayuno</th>
                                <th>Media mañana</th>
                                <th>Almuerzo</th>
                                <th>Algo</th>
                                <th>Merienda</th>
                                <th>Cena</th>
                                <th>Fraccionada</th>
                                <th>Total bandejas</th>
                            </tr>

                        </thead>

                        <tbody>

                            @foreach ($global as $row)
                                <tr>

                                    {{-- <td>{{ $row->tipo_dieta }}</td> --}}

                                    <td>{{ $row->desayuno }}</td>
                                    <td>{{ $row->media_manana }}</td>
                                    <td>{{ $row->almuerzo }}</td>
                                    <td>{{ $row->algo }}</td>
                                    <td>{{ $row->merienda }}</td>
                                    <td>{{ $row->cena }}</td>
                                    <td>{{ $row->fraccionada }}</td>
                                    <td>{{ $row->total_bandejas }}</td>

                                </tr>
                            @endforeach

                        </tbody>

                    </table>



                    {{-- GRÁFICO MENSUAL --}}

                    <h4 class="mt-5">Producción mensual de comidas</h4>

                    <canvas id="monthlyChart"></canvas>

                </div>


                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    
                <script>
                    const labels = @json(array_column($monthly, 'mes'));

                    const desayuno = @json(array_column($monthly, 'desayuno'));
                    const almuerzo = @json(array_column($monthly, 'almuerzo'));
                    const cena = @json(array_column($monthly, 'cena'));

                    new Chart(document.getElementById('monthlyChart'), {

                        type: 'bar',

                        data: {

                            labels: labels,

                            datasets: [

                                {
                                    label: 'Desayuno',
                                    data: desayuno
                                },

                                {
                                    label: 'Almuerzo',
                                    data: almuerzo
                                },

                                {
                                    label: 'Cena',
                                    data: cena
                                }

                            ]

                        },

                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top'
                                }
                            }
                        }

                    });
                </script>
            @endcan
        </div>



        {{-- =========================
            CHART JS
            ========================= --}}

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>




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
