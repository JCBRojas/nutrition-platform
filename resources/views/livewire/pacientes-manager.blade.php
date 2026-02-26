<div>

    {{-- ===================== TABLA ===================== --}}
    <div class="bg-white shadow rounded p-6">
        <h1 class="mb-4 font-semibold">Pacientes registrados</h1>

        @php
            $changeColors = [
                'CANCELADA' => 'bg-red-100 text-red-800',
                'CON CAMBIOS' => 'bg-yellow-100 text-yellow-800',
                'SE AISLÓ' => 'bg-purple-100 text-purple-800',
                'YA NO ESTA AISLADO' => 'bg-green-100 text-green-800',
            ];
        @endphp

        <div class="overflow-x-auto" wire:poll.3s>
            <table class="min-w-full divide-y divide-gray-200">

                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-xs">ID</th>
                        <th class="px-3 py-2 text-xs">Habitación</th>
                        <th class="px-3 py-2 text-xs">Paciente</th>
                        <th class="px-3 py-2 text-xs">Tiempo</th>
                        <th class="px-3 py-2 text-xs">Consistencia</th>
                        <th class="px-3 py-2 text-xs">Especificaciones</th>
                        <th class="px-3 py-2 text-xs">Observaciones</th>
                        <th class="px-3 py-2 text-xs">Aislamiento</th>
                        <th class="px-3 py-2 text-xs">Cambios</th>
                        <th class="px-3 py-2 text-xs text-right">Acciones</th>
                    </tr>
                </thead>

                    <tbody class="divide-y divide-gray-200">
                    @foreach ($pacientes as $paciente)
                    <tr>
                        <td class="px-3 py-2 text-xs">{{ $paciente->id }}</td>
                        <td class="px-3 py-2 text-xs">{{ $paciente->habitation }}</td>
                        <td class="px-3 py-2 text-xs">{{ $paciente->namePatient }}</td>
                        <td class="px-3 py-2 text-xs">
                                @forelse ($paciente->time_food_array as $item)
                                <small class="badge badge-light-success">
                                    {{ ucfirst(str_replace('_', ' ', $item)) }}
                                </small>
                                @empty
                                <span class="text-gray-400">N/A</span>
                                @endforelse
                        </td>
                        <td class="px-3 py-2 text-xs">
                            @forelse ($paciente->consistency_array as $item)
                            <small class="badge badge-light-info">
                                {{ ucfirst(str_replace('_', ' ', $item)) }}
                            </small>
                            @empty
                            <span class="text-gray-400">N/A</span>
                            @endforelse
                        </td>
                        <td class="px-3 py-2 text-xs">
                            @forelse ($paciente->specifications_array as $item)
                            <small class="badge badge-light-primary">
                                {{ ucfirst(str_replace('_', ' ', $item)) }}
                            </small>
                            @empty
                            <span class="text-gray-400">N/A</span>
                            @endforelse
                        </td>
                        <td class="px-3 py-2 text-xs">{{ $paciente->observations }}</td>
                        <td class="px-3 py-2 text-xs">
                            @if ($paciente->isolation)
                            Sí
                            @else
                            No
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs">{{ $paciente->changes }}</td>
                        <td class="px-3 py-2 text-xs text-right">
                            <button type="button" class="btn btn-xs btn-primary">
                                <i class=" mdi mdi-square-edit-outline "></i>
                            </button>
                            <button type="button" class="btn btn-xs btn-danger">Eliminar</button>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
            </table>
        </div>
    </div>

</div>