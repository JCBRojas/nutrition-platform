@php
/* ===========================
| Datos base (null-safe)
|=========================== */
$version = data_get($diet ?? null, 'currentVersion');
$id = $diet ? $diet->id : '';

/* ===========================
| Campos simples
|=========================== */
$habitation = old('habitation', data_get($diet, 'habitation', ''));
$name_patient = old('name_patient', data_get($diet, 'name_patient', ''));
$observations = old('observations', data_get($version, 'observations', ''));
$isolation = old('isolation', data_get($version, 'isolation', 'No'));
$changes = old('changes', data_get($version, 'changes', 'NA'));

/* ===========================
| Campos múltiples (JSON)
|=========================== */
$selectedTimeFood = old('timeFood', data_get($version, 'timeFood', []));
$selectedConsistency = old('consistency', data_get($version, 'consistency', []));
$selectedSpecifications = old('specifications', data_get($version, 'specifications', []));

/* ===========================
| Opciones
|=========================== */
$timeFoodOptions = [
'desayuno' => 'Desayuno',
'media_manana' => 'Media mañana',
'almuerzo' => 'Almuerzo',
'algo' => 'Algo',
'cena' => 'Cena',
'fraccionada' => 'Fraccionada',
];

$consistencyOptions = [
'liquida_completa' => 'Líquida Completa',
'liquida_clara' => 'Líquida Clara',
'muy_blanda' => 'Muy Blanda',
'blanda_mecanica' => 'Blanda mecánica',
'supraglotica' => 'Supraglótica',
'normal' => 'Normal',
];

$specificationOptions = [
'hipoglucida' => 'Hipoglúcida',
'hiperproteica' => 'Hiperprotéica',
'hiposodica' => 'Hiposódica',
'hipograsa' => 'Hipograsa',
'astringente' => 'Astringente',
'alta_en_fibra' => 'Alta en fibra',
'sin_lacteos' => 'Sin lácteos',
'sin_irritantes' => 'Sin irritantes',
'hipercalorica' => 'Hipercalórica',
];
@endphp

{{-- ===========================
 |  FORMULARIO
 |=========================== --}}


{{-- Habitación --}}
<div>
    <label class="block text-sm font-medium">Habitación</label>
    <input type="text"
        name="habitation"
        value="{{ $habitation }}"
        class="w-full border rounded px-2 py-1">
</div>

{{-- Paciente --}}
<div>
    <label class="block text-sm font-medium">Nombre del Paciente</label>
    <input type="text"
        name="name_patient"
        value="{{ $name_patient }}"
        class="w-full border rounded px-2 py-1">
</div>

{{-- Tiempo de comida --}}
<x-multi-select
    name="timeFood"
    label="Tiempo de comida"
    :options="$timeFoodOptions"
    :selected="$selectedTimeFood"
    style="btn-pink" />

{{-- Consistencia --}}
<x-multi-select
    name="consistency"
    label="Consistencia"
    :options="$consistencyOptions"
    :selected="$selectedConsistency"
    style="btn-light" />
{{-- Especificaciones --}}

<div class="form-group">
    <label>Consistencia</label>

    <div class="chip-select blue-focus"
        id="cs-consistency{{ $id ? '-' . $id : '' }}"
        onclick="toggleChipSelect('cs-consistency{{ $id ? '-' . $id : '' }}')">


        <div class="chips-display" id="chips-edit-consistency{{ $id ? '-' . $id : '' }}">

        </div>

        <div class="chip-options" id="opts-edit-consistency{{ $id ? '-' . $id : '' }}">
                        <span class="chip-option" data-val="Liquida completa" onclick="selectChip(event,'cs-consistency','consistency{{ $id ? '-' . $id : '' }}','edit-consistency{{ $id ? '-' . $id : '' }}')" {{ in_array('Liquida completa', $selectedConsistency) ? 'class=chip-option selected' : 'class=chip-option' }}>Liquida completa</span>
                        <span class="chip-option" data-val="Liquida clara" onclick="selectChip(event,'cs-consistency','consistency{{ $id ? '-' . $id : '' }}','edit-consistency{{ $id ? '-' . $id : '' }}')" {{ in_array('Liquida clara', $selectedConsistency) ? 'class=chip-option selected' : 'class=chip-option' }}>Liquida clara</span>
                        <span class="chip-option" data-val="Muy blanda" onclick="selectChip(event,'cs-consistency','consistency{{ $id ? '-' . $id : '' }}','edit-consistency{{ $id ? '-' . $id : '' }}')" {{ in_array('Muy blanda', $selectedConsistency) ? 'class=chip-option selected' : 'class=chip-option' }}>Muy blanda</span>
                        <span class="chip-option" data-val="Blanda mecanica" onclick="selectChip(event,'cs-consistency','consistency{{ $id ? '-' . $id : '' }}','edit-consistency{{ $id ? '-' . $id : '' }}')" {{ in_array('Blanda mecanica', $selectedConsistency) ? 'class=chip-option selected' : 'class=chip-option' }}>Blanda mecánica</span>
                        <span class="chip-option" data-val="Normal" onclick="selectChip(event,'cs-consistency','consistency{{ $id ? '-' . $id : '' }}','edit-consistency{{ $id ? '-' . $id : '' }}')" {{ in_array('Normal', $selectedConsistency) ? 'class=chip-option selected' : 'class=chip-option' }}>Normal</span>
                        <span class="chip-option" data-val="Papilla" onclick="selectChip(event,'cs-consistency{{ $id ? '-' . $id : '' }}','edit-consistency{{ $id ? '-' . $id : '' }}')" {{ in_array('Sin papilla', $selectedConsistency) ? 'class=chip-option selected' : 'class=chip-option' }}>Papilla</span>

        </div>
    </div>

    <div id="hidden-edit-consistency{{ $id ? '-' . $id : '' }}">
    </div>
</div>



{{-- Especificaciones --}}

<div class="form-group">
    <label>Especificaciones</label>

    <div class="chip-select blue-focus"
        id="cs-specifications{{ $id ? '-' . $id : '' }}"
        onclick="toggleChipSelect('cs-specifications{{ $id ? '-' . $id : '' }}')">


        <div class="chips-display" id="chips-edit-specifications{{ $id ? '-' . $id : '' }}">

        </div>

        <div class="chip-options" id="opts-edit-specifications{{ $id ? '-' . $id : '' }}">
            <span class="chip-option" data-val="Sin irritantes" onclick="selectChip(event,'cs-specifications{{ $id ? '-' . $id : '' }}','edit-specifications{{ $id ? '-' . $id : '' }}')" {{ in_array('Sin irritantes', $selectedSpecifications) ? 'class=chip-option selected' : 'class=chip-option' }}>Sin irritantes</span>
            <span class="chip-option" data-val="Hipercalorica" onclick="selectChip(event,'cs-specifications{{ $id ? '-' . $id : '' }}','edit-specifications{{ $id ? '-' . $id : '' }}')" {{ in_array('Hipercalorica', $selectedSpecifications) ? 'class=chip-option selected' : 'class=chip-option' }}>Hipercalórica</span>
            <span class="chip-option" data-val="Hiperproteica" onclick="selectChip(event,'cs-specifications{{ $id ? '-' . $id : '' }}','edit-specifications{{ $id ? '-' . $id : '' }}')" {{ in_array('Hiperproteica', $selectedSpecifications) ? 'class=chip-option selected' : 'class=chip-option' }}>Hiperproteica</span>
            <span class="chip-option" data-val="Hiposodica" onclick="selectChip(event,'cs-specifications{{ $id ? '-' . $id : '' }}','edit-specifications{{ $id ? '-' . $id : '' }}')" {{ in_array('Hiposodica', $selectedSpecifications) ? 'class=chip-option selected' : 'class=chip-option' }}>Hiposódica</span>
            <span class="chip-option" data-val="Hipograsa" onclick="selectChip(event,'cs-specifications{{ $id ? '-' . $id : '' }}','edit-specifications{{ $id ? '-' . $id : '' }}')" {{ in_array('Hipograsa', $selectedSpecifications) ? 'class=chip-option selected' : 'class=chip-option' }}>Hipograsa</span>
            <span class="chip-option" data-val="Hipoglucida" onclick="selectChip(event,'cs-specifications{{ $id ? '-' . $id : '' }}','edit-specifications{{ $id ? '-' . $id : '' }}')" {{ in_array('Hipoglucida', $selectedSpecifications) ? 'class=chip-option selected' : 'class=chip-option' }}>Hipoglúcida</span>
            <span class="chip-option" data-val="Astringente" onclick="selectChip(event,'cs-specifications{{ $id ? '-' . $id : '' }}','edit-specifications{{ $id ? '-' . $id : '' }}')" {{ in_array('Astringente', $selectedSpecifications) ? 'class=chip-option selected' : 'class=chip-option' }}>Astringente</span>
            <span class="chip-option" data-val="Sin lacteos" onclick="selectChip(event,'cs-specifications{{ $id ? '-' . $id : '' }}','edit-specifications{{ $id ? '-' . $id : '' }}')" {{ in_array('Sin lacteos', $selectedSpecifications) ? 'class=chip-option selected' : 'class=chip-option' }}>Sin lácteos</span>
            <span class="chip-option" data-val="Sin gluten" onclick="selectChip(event,'cs-specifications{{ $id ? '-' . $id : '' }}','edit-specifications{{ $id ? '-' . $id : '' }}')" {{ in_array('Sin gluten', $selectedSpecifications) ? 'class=chip-option selected' : 'class=chip-option' }}>Sin gluten</span>
            <span class="chip-option" data-val="Sin fibra" onclick="selectChip(event,'cs-specifications{{ $id ? '-' . $id : '' }}','edit-specifications{{ $id ? '-' . $id : '' }}')" {{ in_array('Sin fibra', $selectedSpecifications) ? 'class=chip-option selected' : 'class=chip-option' }}>Sin fibra</span>
        </div>
    </div>

    <div id="hidden-edit-specifications{{ $id ? '-' . $id : '' }}">
    </div>
</div>

{{-- Observaciones --}}
<div>
    <label class="block text-sm font-medium">Observaciones</label>
    <input type="text"
        name="observations"
        value="{{ $observations }}"
        class="w-full border rounded px-2 py-1">
</div>

{{-- Aislamiento --}}
<div>
    <label class="block text-sm font-medium">Aislamiento</label>
    <select name="isolation" class="form-control">
        <option value="Sí" {{ $isolation === 'Sí' ? 'selected' : '' }}>Sí</option>
        <option value="No" {{ $isolation === 'No' ? 'selected' : '' }}>No</option>
    </select>
</div>

{{-- Cambios --}}
<div>
    <label class="block text-sm font-medium">Cambios en la dieta</label>
    <select name="changes" class="form-control">
        <option value="NA" {{ $changes === 'NA' ? 'selected' : '' }}>N/A</option>
        <option value="cancelada" {{ $changes === 'cancelada' ? 'selected' : '' }}>Cancelada</option>
        <option value="con_cambios" {{ $changes === 'con_cambios' ? 'selected' : '' }}>Con cambios</option>
        <option value="aislado" {{ $changes === 'aislado' ? 'selected' : '' }}>Se aisló</option>
        <option value="ya_no_aislado" {{ $changes === 'ya_no_aislado' ? 'selected' : '' }}>Ya no está aislado</option>
    </select>
</div>

{{-- Botón --}}


        <button type="submit" >
            Guardar
        </button>
