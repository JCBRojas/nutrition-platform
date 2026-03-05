<div>

    {{-- ===================== TABLA ===================== --}}
    <div class="bg-white shadow rounded p-6">
        <h1 class="mb-4 font-semibold">Pacientes</h1>

        @php
        $changeColors = [
        'cancelada' => 'bg-red-100 text-red-800',
        'con_cambios' => 'bg-yellow-100 text-yellow-800',
        'aislado' => 'bg-purple-100 text-purple-800',
        'ya_no_aislado' => 'bg-green-100 text-green-800',
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
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">

                    @foreach ($diets as $diet)

                    @php
                    $changes = $diet->version_changes ?? [];
                    $current = $diet->currentVersion;
                    @endphp

                    <tr class="{{ !empty($changes) ? 'bg-yellow-50 border-l-4 border-yellow-400' : '' }}">

                        <td class="px-3 py-2 text-xs">{{ $diet->id }}</td>
                        <td class="px-3 py-2 text-xs">{{ $diet->habitation }}</td>
                        <td class="px-3 py-2 text-xs">{{ $diet->namePatient }}</td>

                        {{-- ===================== TIME FOOD ===================== --}}
                        <td class="px-3 py-2 text-xs">

                            @forelse ($current->timeFood ?? [] as $item)

                            <span class="badge
                                    {{ isset($changes['timeFood']) ? 'badge-light-success' : 'badge-light-primary' }}">
                                {{ ucfirst(str_replace('_',' ',$item)) }}
                            </span>

                            @empty
                            <span class="text-gray-400">N/A</span>
                            @endforelse

                            @if(isset($changes['timeFood']))
                            <div class="text-xs text-gray-500 mt-1">

                                Antes:

                                @foreach((array) $changes['timeFood']['old'] as $old)

                                <span class="badge badge-light-danger">
                                <s>{{ ucfirst(str_replace('_',' ',$old)) }}</s>
                                </span>

                                @endforeach

                            </div>
                            @endif

                        </td>

                        {{-- ===================== CONSISTENCIA ===================== --}}
                        <td class="px-3 py-2 text-xs">

                            @forelse ($current->consistency ?? [] as $item)

                            <span class="badge
                                    {{ isset($changes['consistency']) ? 'badge-light-success' : 'badge-light-info' }}">
                                {{ ucfirst(str_replace('_',' ',$item)) }}
                            </span>

                            @empty
                            <span class="text-gray-400">N/A</span>
                            @endforelse

                            @if(isset($changes['consistency']))
                            <div class="text-xs text-gray-500 mt-1">
                                Antes:

                                @foreach($changes['consistency']['old'] ?? [] as $old)
                                <span class="badge badge-light-danger">
                                   <s>{{ ucfirst(str_replace('_',' ',$old)) }}</s>
                                </span>
                                @endforeach
                            </div>
                            @endif

                        </td>

                        {{-- ===================== ESPECIFICACIONES ===================== --}}
                        <td class="px-3 py-2 text-xs">

                            @forelse ($current->specifications ?? [] as $item)

                            <span
                                class="badge
                                    {{ isset($changes['specifications']) ? 'badge-light-success' : 'badge-light-success' }}">
                                {{ ucfirst(str_replace('_',' ',$item)) }}
                            </span>

                            @empty
                            <span class="text-gray-400">N/A</span>
                            @endforelse

                            @if(isset($changes['specifications']))
                            <div class="text-xs text-gray-500 mt-1">
                                Antes:

                                @foreach($changes['specifications']['old'] ?? [] as $old)
                                <span class="badge badge-light-danger">
                                  <s>{{ ucfirst(str_replace('_',' ',$old)) }}</s>
                                </span>
                                @endforeach
                            </div>
                            @endif

                        </td>

                        {{-- ===================== OBSERVACIONES ===================== --}}
                        <td class="px-3 py-2 text-xs">

                            <span class="{{ isset($changes['observations']) ? 'text-yellow-600 font-semibold' : '' }}">
                                {{ $current->observations ?? '' }}
                            </span>

                        </td>

                        {{-- ===================== AISLAMIENTO ===================== --}}
                        <td class="px-3 py-2 text-xs">

                            <span class="{{ isset($changes['isolation']) ? 'text-yellow-600 font-semibold' : '' }}">
                                {{ $current->isolation ?? '' }}
                            </span>

                        </td>

                        {{-- ===================== CAMBIOS ===================== --}}
                        <td class="px-3 py-2 text-xs">

                            @php
                            $change = $current->changes ?? null;
                            @endphp

                            @if($change)

                            <span class="px-2 py-1 rounded text-xs
                                    {{ $changeColors[$change] ?? 'bg-gray-100 text-gray-800' }}">

                                {{ strtoupper(str_replace('_',' ', $change)) }}

                            </span>

                            @endif

                        </td>

                    </tr>

                    @endforeach

                </tbody>

            </table>
        </div>
    </div>

</div>