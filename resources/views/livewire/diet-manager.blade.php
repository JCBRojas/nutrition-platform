<div x-data="{ tab: 'active' }">

    {{-- ===================== CONTROLES ===================== --}}
    <div class="flex gap-2 mb-4">

        <button @click="tab='active'" :class="tab === 'active' ? 'bg-blue-600 text-white' : 'bg-gray-200'"
            class="px-4 py-2 rounded text-sm font-medium">
            Dietas Activas
        </button>

        <button @click="tab='cancelled'" :class="tab === 'cancelled' ? 'bg-red-600 text-white' : 'bg-gray-200'"
            class="px-4 py-2 rounded text-sm font-medium">
            Dietas Canceladas
        </button>

    </div>

    @php
    $activeDiets = $diets->where('currentVersion.changes','!=','cancelada');
    $cancelledDiets = $diets->where('currentVersion.changes','cancelada');
    @endphp


    {{-- ===================== TABLA ACTIVAS ===================== --}}
    <div x-show="tab === 'active'" class="bg-white shadow rounded p-6">

        <h1 class="mb-4 font-semibold">Dietas Activas</h1>

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
                        {{-- <th class="px-3 py-2 text-xs">Acción</th> --}}
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">

                    @foreach ($activeDiets as $diet)

                    @php
                    $changes = $diet->version_changes ?? [];
                    $current = $diet->currentVersion;

                    @endphp

                    <tr class="{{ $current->modify === 'si' ? 'bg-yellow-50 border-l-4 border-yellow-400' : '' }}">

                        {{-- <td class="px-3 py-2 text-xs">{{ $diet->id }}</td> --}}
                        <td><button type="checkbox"
                             wire:click="confirmChanges({{$current->id}})">
                             {{ $current->modify === 'si' ? '🔵' : '' }}
                            </button></td>
                        <td class="px-3 py-2 text-xs">{{ $diet->habitation }}</td>
                        <td class="px-3 py-2 text-xs">{{ $diet->name_patient }}</td>

                        {{-- TIEMPO --}}
                        <x-badges :items="$current->timeFood ?? []" :changes="$changes['timeFood'] ?? null"
                            category="tiempo" />

                        {{-- CONSISTENCIA --}}
                        <x-badges :items="$current->consistency ?? []" :changes="$changes['consistency'] ?? null"
                            category="consist" />

                        {{-- ESPECIFICACIONES --}}
                        <x-badges :items="$current->specifications ?? []" :changes="$changes['specifications'] ?? null"
                            category="espec" />

                        {{-- OBSERVACIONES --}}
                        <td class="px-3 py-2 text-xs">
                            <span class="{{ isset($changes['observations']) ? 'text-yellow-600 font-semibold' : '' }}">
                                {{ $current->observations ?? '' }}
                            </span>
                        </td>

                        {{-- AISLAMIENTO --}}
                        <td class="px-3 py-2 text-xs">
                            <span class="{{ isset($changes['isolation']) ? 'text-yellow-600 font-semibold' : '' }}">
                                {{ $current->isolation ?? '' }}
                            </span>
                        </td>

                        {{-- CAMBIOS --}}
                        <x-change-color :type="$current->changes ?? null">
                            {{ strtoupper(str_replace('_',' ', $current->changes ?? '')) }}
                        </x-change-color>

                        {{-- ACCIÓN --}}
                        {{-- <td class="px-3 py-2 text-xs">

                            @if($changes)
                            <button wire:click="confirmChanges({{ $current->id }})"
                                class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700">
                                Confirmar
                            </button>
                            @else
                            <span class="text-gray-400 text-xs">
                                Sin cambios
                            </span>
                            @endif

                        </td> --}}

                    </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

    </div>


    {{-- ===================== TABLA CANCELADAS ===================== --}}
    <div x-show="tab === 'cancelled'" class="bg-white shadow rounded p-6">

        <h1 class="mb-4 font-semibold text-red-600">
            Dietas Canceladas
        </h1>

        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-gray-200">

                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-xs">ID</th>
                        <th class="px-3 py-2 text-xs">Habitación</th>
                        <th class="px-3 py-2 text-xs">Paciente</th>
                        <th class="px-3 py-2 text-xs">Observaciones</th>
                        <th class="px-3 py-2 text-xs">Estado</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">

                    @forelse ($cancelledDiets as $diet)

                    @php
                    $current = $diet->currentVersion;
                    @endphp

                    <tr class="bg-red-50 border-l-4 border-red-400">

                        <td class="px-3 py-2 text-xs">
                            {{ $diet->id }}
                        </td>

                        <td class="px-3 py-2 text-xs">
                            {{ $diet->habitation }}
                        </td>

                        <td class="px-3 py-2 text-xs">
                            {{ $diet->name_patient }}
                        </td>

                        <td class="px-3 py-2 text-xs">
                            {{ $current->observations ?? 'N/A' }}
                        </td>

                        <td class="px-3 py-2 text-xs">

                            <span class="px-2 py-1 rounded text-xs bg-red-100 text-red-800">
                                CANCELADA
                            </span>

                        </td>

                    </tr>

                    @empty

                    <tr>
                        <td colspan="5" class="px-3 py-4 text-center text-gray-400 text-sm">
                            No hay dietas canceladas
                        </td>
                    </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>