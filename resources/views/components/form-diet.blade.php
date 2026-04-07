{{-- resources/views/diets/form.blade.php --}}

@php
/* ===========================
 |  Datos base (null-safe)
 |=========================== */
$version = data_get($diet ?? null, 'currentVersion');

/* ===========================
 |  Campos simples
 |=========================== */
$habitation   = old('habitation', data_get($diet, 'habitation', ''));
$name_patient  = old('name_patient', data_get($diet, 'name_patient', ''));
$observations = old('observations', data_get($version, 'observations', ''));
$isolation    = old('isolation', data_get($version, 'isolation', 'no'));
$changes      = old('changes', data_get($version, 'changes', 'NA'));

/* ===========================
 |  Campos múltiples (JSON)
 |=========================== */
$selectedTimeFood = old('timeFood', data_get($version, 'timeFood', []));
$selectedConsistency = old('consistency', data_get($version, 'consistency', []));
$selectedSpecifications = old('specifications', data_get($version, 'specifications', []));

/* ===========================
 |  Opciones
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

<div class="space-y-3">

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
        style="btn-pink"
    />

    {{-- Consistencia --}}
    <x-multi-select
        name="consistency"
        label="Consistencia"
        :options="$consistencyOptions"
        :selected="$selectedConsistency"
        style="btn-light"
    />

    {{-- Especificaciones --}}
    <x-multi-select
        name="specifications"
        label="Especificaciones"
        :options="$specificationOptions"
        :selected="$selectedSpecifications"
        style="btn-primary"
    />

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
            <option value="Si" {{ $isolation === 'Si' ? 'selected' : '' }}>Sí</option>
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
    <div class="pt-3 text-right">
        <button type="submit"
                class="btn btn-dark px-4 py-1 rounded">
            Guardar
        </button>
    </div>

</div>

{{-- ===========================
 |  Selectpicker refresh
 |=========================== --}}
