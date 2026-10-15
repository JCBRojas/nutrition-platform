@php
    /* ===========================
| Datos base (null-safe)
|=========================== */
    $version = data_get($diet ?? null, 'currentVersion');
    $id = $diet ? $diet->id : '';

    $selectedTimeFood = old('timeFood', data_get($version, 'timeFood', []));
    $selectedConsistency = old('consistency', data_get($version, 'consistency', []));
    $selectedSpecifications = old('specifications', data_get($version, 'specifications', []));
@endphp
<div class="modal">
    <div class="modal-header">
        <h2 id="modalTitle">Editar Dieta</h2>
        <button class="close-btn" onclick="closeModal()">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>
    </div>
    <form method="POST" action="{{ route('dietas.update', $diet->id) }}">
        @csrf
        @method('put')
        <div class="modal-body">

            {{-- Habitación --}}
            <div>
                <label class="block text-sm font-medium">Habitación</label>
                <input type="text" name="habitation" id="edit-habitacion-{{ $diet->id }}"
                    class="w-full border rounded px-2 py-1">
            </div>

            {{-- Paciente --}}
            <div>
                <label class="block text-sm font-medium">Nombre del Paciente</label>
                <input type="text" name="name_patient" id="edit-paciente-{{ $diet->id }}"
                    class="w-full border rounded px-2 py-1">
            </div>

            <div class="form-group">
                <label>Tiempo</label>

                <div class="chip-select blue-focus" id="cs-timeFood-{{ $id }}"
                    onclick="toggleChipSelect('cs-timeFood-{{ $id }}')">

                    <div class="chips-display" id="chips-edit-timeFood-{{ $id }}"></div>

                    <div class="chip-options" id="opts-edit-timeFood-{{ $id }}">
                        @foreach ($foodLabels as $key => $opt)
                            <span class="chip-option {{ in_array($opt, $selectedTimeFood) ? 'selected' : '' }}"
                                data-val="{{ $key }}"
                                onclick="selectChip(event, 'cs-timeFood-{{ $id }}', 'edit-timeFood-{{ $id }}')">
                                {{ $opt }}
                            </span>
                        @endforeach
                    </div>
                </div>
                <div id="hidden-edit-timeFood-{{ $id }}"></div>
            </div>

            <div class="form-group">
                <label>Consistencia</label>
                <div class="chip-select blue-focus" id="cs-consistency{{ $id ? '-' . $id : '' }}"
                    onclick="toggleChipSelect('cs-consistency{{ $id ? '-' . $id : '' }}')">
                    <div class="chips-display" id="chips-edit-consistency{{ $id ? '-' . $id : '' }}">
                    </div>
                    <div class="chip-options" id="opts-edit-consistency{{ $id ? '-' . $id : '' }}">

                        @foreach ($consistencyLabels as $key => $opt)
                            <span class="chip-option" data-val="{{ $key }}"
                                onclick="selectChip(event,
                        'cs-consistency{{ $id ? '-' . $id : '' }}',
                        'edit-consistency{{ $id ? '-' . $id : '' }}'
                  )"
                                class="chip-option {{ in_array($opt, $selectedConsistency) ? 'selected' : '' }}">
                                {{ $opt }}
                            </span>
                        @endforeach

                    </div>
                </div>

                <div id="hidden-edit-consistency{{ $id ? '-' . $id : '' }}">
                </div>
            </div>



            {{-- Especificaciones --}}

            <div class="form-group">
                <label>Especificaciones</label>

                <div class="chip-select blue-focus" id="cs-specifications{{ $id ? '-' . $id : '' }}"
                    onclick="toggleChipSelect('cs-specifications{{ $id ? '-' . $id : '' }}')">

                    <div class="chips-display" id="chips-edit-specifications{{ $id ? '-' . $id : '' }}">
                    </div>

                    <div class="chip-options" id="opts-edit-specifications{{ $id ? '-' . $id : '' }}">

                        @foreach ($specLabels as $key => $opt)
                            <span class="chip-option" data-val="{{ $key }}"
                                onclick="selectChip(event,
                        'cs-specifications{{ $id ? '-' . $id : '' }}',
                        'edit-specifications{{ $id ? '-' . $id : '' }}'
                  )"
                                class="chip-option {{ in_array($opt, $selectedSpecifications) ? 'selected' : '' }}">
                                {{ $opt }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <div id="hidden-edit-specifications{{ $id ? '-' . $id : '' }}"></div>

            </div>

            {{-- Observaciones --}}
            <div>
                <label class="block text-sm font-medium">Observaciones</label>
                <input type="text" name="observations" id="edit-observaciones-{{ $diet->id }}"
                    class="w-full border rounded px-2 py-1">
            </div>

            {{-- Aislamiento --}}
            <div>
                <label class="block text-sm font-medium">Aislamiento</label>
                <select name="isolation" class="form-control" id="edit-isolation-{{ $diet->id }}">
                    <option value="Si">Sí</option>
                    <option value="No">No</option>
                </select>
            </div>

            {{-- Cambios --}}
            <div>
                <label class="block text-sm font-medium">Cambios en la dieta</label>
                <select name="changes" id="edit-changes-{{ $diet->id }}" class="form-control">
                    <option value="NA">N/A</option>
                    <option value="cancelada">Cancelada</option>
                    <option value="con_cambios">Con cambios</option>
                    {{-- <option value="aislado">Se aisló</option> --}}
                    {{-- <option value="ya_no_aislado">Ya no está aislado</option> --}}
                </select>

            </div>

            {{-- Botón --}}
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" type="submit">
                Actualizar
            </button>
        </div>
    </form>

</div><!-- /.modal -->
