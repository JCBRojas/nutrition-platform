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


<h1 class="text-2xl font-bold text-center">
Reporte Nutricional de Dietas
</h1>
               {{-- =========================
TOTALES GLOBALES
========================= --}}

<div class="mt-4">

<h2 class="text-xl font-semibold mb-2 text-primary">
Totales Globales
</h2>

<div class="grid grid-cols-4 gap-4">

<div class="bg-blue-100 p-4 rounded text-center">
<p class="font-semibold">Desayuno</p>
<p class="text-2xl">{{ $globalTotals->desayuno }}</p>
</div>

<div class="bg-green-100 p-4 rounded text-center">
<p class="font-semibold">Almuerzo</p>
<p class="text-2xl">{{ $globalTotals->almuerzo }}</p>
</div>

<div class="bg-purple-100 p-4 rounded text-center">
<p class="font-semibold">Cena</p>
<p class="text-2xl">{{ $globalTotals->cena }}</p>
</div>

<div class="bg-yellow-100 p-4 rounded text-center">
<p class="font-semibold">Fraccionada</p>
<p class="text-2xl">{{ $globalTotals->fraccionada }}</p>
</div>

</div>

</div>



{{-- =========================
GRÁFICO MENSUAL
========================= --}}

<div class="mt-4">

<h2 class="text-xl font-semibold mb-2">
Producción Mensual de Comidas
</h2>

<canvas id="nutritionChart"></canvas>

</div>



{{-- =========================
TOTALES DIARIOS
========================= --}}

<div class="mt-4">

<h2 class="text-xl font-semibold mb-2">
Totales Diarios
</h2>

<div class="overflow-x-auto">

<table class="min-w-full border border-gray-200">

<thead class="bg-gray-100">

<tr>

<th class="p-2 border">Fecha</th>
<th class="p-2 border">Desayuno</th>
<th class="p-2 border">Media Mañana</th>
<th class="p-2 border">Almuerzo</th>
<th class="p-2 border">Algo</th>
<th class="p-2 border">Cena</th>
<th class="p-2 border">Fraccionada</th>

</tr>

</thead>

<tbody>

@foreach($dailyTotals as $row)

<tr class="text-center">

<td class="border p-2">{{ $row->fecha }}</td>
<td class="border p-2">{{ $row->desayuno }}</td>
<td class="border p-2">{{ $row->media_manana }}</td>
<td class="border p-2">{{ $row->almuerzo }}</td>
<td class="border p-2">{{ $row->algo }}</td>
<td class="border p-2">{{ $row->cena }}</td>
<td class="border p-2">{{ $row->fraccionada }}</td>

</tr>

@endforeach

</tbody>
<tfoot>
<tr class="font-semibold font-16 text-center bg-gray-200">
<td class="border p-2 text-danger">Total</td>
<td class="border p-2">{{ array_sum(array_column($dailyTotals, 'desayuno')) }}</td>
<td class="border p-2">{{ array_sum(array_column($dailyTotals, 'media_manana')) }}</td>
<td class="border p-2">{{ array_sum(array_column($dailyTotals, 'almuerzo')) }}</td>
<td class="border p-2">{{ array_sum(array_column($dailyTotals, 'algo')) }}</td>
<td class="border p-2">{{ array_sum(array_column($dailyTotals, 'cena')) }}</td>
<td class="border p-2">{{ array_sum(array_column($dailyTotals, 'fraccionada')) }}</td>
</tr>

</tfoot>
</table>

</div>

</div>



{{-- =========================
TOTALES MENSUALES
========================= --}}

<div class="mt-4">

<h2 class="text-xl font-semibold mb-2">
Totales Mensuales
</h2>

<div class="overflow-x-auto">

<table class="min-w-full border border-gray-200">

<thead class="bg-gray-100">

<tr>

<th class="p-2 border">Mes</th>
<th class="p-2 border">Desayuno</th>
<th class="p-2 border">Media Mañana</th>
<th class="p-2 border">Almuerzo</th>
<th class="p-2 border">Algo</th>
<th class="p-2 border">Cena</th>
<th class="p-2 border">Fraccionada</th>

</tr>

</thead>

<tbody>

@foreach($monthlyTotals as $row)

<tr class="text-center">

<td class="border p-2">{{ $row->mes }}</td>
<td class="border p-2">{{ $row->desayuno }}</td>
<td class="border p-2">{{ $row->media_manana }}</td>
<td class="border p-2">{{ $row->almuerzo }}</td>
<td class="border p-2">{{ $row->algo }}</td>
<td class="border p-2">{{ $row->cena }}</td>
<td class="border p-2">{{ $row->fraccionada }}</td>

</tr>

@endforeach

</tbody>


</table>

</div>

</div>

</div>



<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const labels = @json($chartLabels);

const desayuno = @json($chartBreakfast);
const almuerzo = @json($chartLunch);
const cena = @json($chartDinner);

new Chart(document.getElementById('nutritionChart'),{

type:'bar',

data:{

labels:labels,

datasets:[

{
label:'Desayuno',
data:desayuno
},

{
label:'Almuerzo',
data:almuerzo
},

{
label:'Cena',
data:cena
}

]

},

options:{
responsive:true,
plugins:{
legend:{
position:'top'
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