<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <form action="{{ route('pacientes.save') }}" method="POST">
            @csrf
            <input type="text" name="habitation" placeholder="Habitación" class="w-full border rounded px-2 py-1 mb-4">
            <input type="text" name="namePatient" placeholder="Nombre Paciente" class="w-full border rounded px-2 py-1 mb-4">
            <input type="text" name="timeFood" placeholder="Tiempo de Comida" class="w-full border rounded px-2 py-1 mb-4">
            <input type="text" name="consistency" placeholder="Consistencia" class="w-full border rounded px-2 py-1 mb-4">
            <input type="text" name="specifications" placeholder="Especificaciones" class="w-full border rounded px-2 py-1 mb-4">
            <input type="text" name="observations" placeholder="Observaciones" class="w-full border rounded px-2 py-1 mb-4">
            <input type="checkbox" name="isolation"> Aislamiento
            <input type="text" name="changes" placeholder="Cambios en la dieta (opcional)" class="w-full border rounded px-2 py-1 mb-4">
            <button type="submit" class="bg-dark px-3 py-1 rounded">Agregar Paciente</button>
        </form>
        </div>
    </div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <h1 class="mb-4">Welcome to the Dashboard Nutrición</h1>

                <ul id="pacientes-list" class="list-disc pl-6">
                    <!-- Aquí se irán agregando pacientes en tiempo real -->
                </ul>
            </div>
        </div>
    </div>

    <script>
        window.Echo.channel('pacientes')
            .listen('PacienteActualizado', (e) => {
                const list = document.getElementById('pacientes-list');
                const item = document.createElement('li');
                item.textContent = `${e.paciente.nombre} - Habitación ${e.paciente.habitacion}`;
                list.appendChild(item);
            });
    </script>
</x-app-layout>