<div class="modal">
    <div class="modal-header">
        <h2 id="modalTitle">Registrar Dieta</h2>
        <button class="close-btn" onclick="closeModal()">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>
    </div>

    <form id="dietForm" method="POST" action="{{ route('dietas.create') }}">
        @csrf
        <div class="modal-body">
            <div id="dupWarning" class="info-banner" style="display:none">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <span id="dupWarningText"></span>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Habitación <span class="req">*</span></label>
                    <input type="text" id="f-habitacion" name="habitation" placeholder="Ej. 205"
                        oninput="checkDuplicate()" />
                </div>
                <div class="form-group">
                    <label>Nombre del Paciente <span class="req">*</span></label>
                    <input type="text" id="f-paciente" name="name_patient" placeholder="Nombre completo" />
                </div>
            </div>
            <!-- ══════════════════════════════════════════════
            TIME FOOD – CHIP SELECT
            ══════════════════════════════════════════════ -->

            <div class="form-group">
                <label>Tiempo de comida</label>

                <div class="chip-select blue-focus" id="cs-timeFood" onclick="toggleChipSelect('cs-timeFood')">

                    <div class="chips-display" id="chips-timeFood"></div>

                    <div class="chip-options" id="opts-timeFood">
                        @foreach ($foodLabels as $key => $label)
                            <span class="chip-option" data-val="{{ $key }}"
                                onclick="selectChip(event,'cs-timeFood','timeFood','{{ $label }}')">
                                {{ $label }}
                            </span>
                        @endforeach
                    </div>
                </div>
                <div id="hidden-timeFood"></div>

            </div>
            <!-- ══════════════════════════════════════════════
            CONSISTENCY – CHIP SELECT
            ══════════════════════════════════════════════ -->

            <div class="form-group">
                <label>Consistencia</label>

                <div class="chip-select blue-focus" id="cs-consistency" onclick="toggleChipSelect('cs-consistency')">

                    <div class="chips-display" id="chips-consistency"></div>

                    <div class="chip-options" id="opts-consistency">
                        @foreach ($consistencyLabels as $key => $label)
                            <span class="chip-option" data-val="{{ $key }}"
                                onclick="selectChip(event,'cs-consistency','consistency','{{ $label }}')">
                                {{ $label }}
                            </span>
                        @endforeach
                    </div>
                </div>
                <!-- Inputs hidden -->
                <div id="hidden-consistency"></div>
            </div>

            <!-- ══════════════════════════════════════════════
            SPECIFICATIONS – CHIP SELECT
            ══════════════════════════════════════════════ -->

            <div class="form-group">
                <label>Especificaciones</label>

                <div class="chip-select blue-focus" id="cs-specifications"
                    onclick="toggleChipSelect('cs-specifications')">

                    <div class="chips-display" id="chips-specifications"></div>

                    <div class="chip-options" id="opts-specifications">
                        @foreach ($specLabels as $key => $label)
                            <span class="chip-option" data-val="{{ $key }}"
                                onclick="selectChip(event,'cs-specifications','specifications','{{ $label }}')">
                                {{ $label }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <!-- Inputs hidden -->
                <div id="hidden-specifications"></div>
            </div>

            <div class="form-group">
                <label>Observaciones</label>
                <textarea id="f-observaciones" name="observations" placeholder="Notas adicionales para cocina…"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Aislamiento</label>
                    <select id="f-aislamiento" name="isolation">
                        <option value="No">No</option>
                        <option value="Sí">Sí</option>
                    </select>
                </div>
                {{-- <div class="form-group">
                    <label>Cambios en la dieta</label>
                    <select id="f-cambios" name="changes" onchange="toggleReasonCategory()">
                        <option value="NA">N/A</option>
                        <option value="cancelada">Cancelada</option>
                        <option value="con_cambios">Con cambios</option>
                        <option value="aislado">Se aisló</option>
                        <option value="no_aislado">Ya no está aislado</option>
                    </select>
                </div> --}}
            </div>
            {{-- <div id="editReasonGroup" style="display:none">
                <div class="form-group">
                    <label>Categoría del cambio <span class="req">*</span></label>
                    <select id="f-motivoCategoria" name="motivo_categoria">
                        <option value="">Seleccione una categoría…</option>
                        <option>Cambio por evolución clínica</option>
                        <option>Intolerancia reportada</option>
                        <option>Orden médica</option>
                        <option>Error de registro</option>
                        <option>Cambio de servicio o habitación</option>
                        <option>Ajuste nutricional</option>
                        <option>Otro</option>
                    </select>
                </div> 
            </div>--}}
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" type="submit" id="saveBtnText"></button>
        </div>
    </form>
</div>
</div>
