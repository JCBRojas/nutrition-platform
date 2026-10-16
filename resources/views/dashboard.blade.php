<x-app-layout>

    <div class="content" id="page-reportes">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">

            @if (session('success'))
                <div class="info-banner mb-4" role="status">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="danger-banner mb-4" role="alert">
                    No se guardó la dieta: {{ $errors->first() }}
                </div>
            @endif

            {{-- Despachos de cocina por confirmar recepción (se actualiza solo) --}}
            <livewire:dispatch-receipts />

            {{-- Indicadores, pestañas y tablas: se actualizan sin recargar (App\Livewire\NutritionDiets) --}}
            <livewire:nutrition-diets />

            {{-- Modal de registro: fuera del componente para que el refresco no lo toque --}}
            <div class="overlay" id="modalOverlay">
                <x-modal-create-diets :specLabels="$specLabels" :consistencyLabels="$consistencyLabels"
                    :foodLabels="$foodLabels"></x-modal-create-diets>
            </div>

        </div>
    </div>
<style>
    /* Acciones de fila: en una sola línea, alineadas a la derecha, mismo alto */
    .row-actions { display: flex; justify-content: flex-end; align-items: center; gap: 6px; flex-wrap: nowrap; }
    .row-actions form { display: contents; }
    .row-actions .btn { white-space: nowrap; justify-content: center; }
    /* Botones solo icono: área táctil de 34px, el nombre va en title + aria-label */
    .btn-icon { width: 34px; height: 34px; padding: 0 !important; display: inline-flex; align-items: center; justify-content: center; }
    .btn-icon svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .btn-icon:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    .tag-finished { background: var(--blue-light); color: var(--blue); font-weight: 600; }
    .table-wrap td:last-child { width: 1%; white-space: nowrap; }
    /* Pestaña seleccionada */
    .tab-btn.active { background: var(--blue-light); color: var(--blue); border-color: #bfdbfe; font-weight: 600; }
    /* Búsqueda de dietas activas */
    .nd-search { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 12px 14px; border-bottom: 1px solid var(--border); }
    .nd-search-spinner { width: 14px; height: 14px; border: 2px solid var(--border); border-top-color: var(--blue); border-radius: 50%; animation: nd-spin .7s linear infinite; }
    @keyframes nd-spin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { .nd-search-spinner { animation: none; } }
    .nd-search .filter-input { max-width: 280px; }
    .nd-search-info { font-size: 12.5px; color: var(--text-muted); }
    .nd-legend { margin: 0 0 0 auto; }
    @media (max-width: 640px) { .nd-search .filter-input { max-width: none; flex: 1 1 100%; } .nd-legend { margin-left: 0; } }
</style>
<script>
    const SPEC_LABELS = @json($specLabels);
    const TIME_LABELS = @json($foodLabels);
    const CONS_LABELS = @json($consistencyLabels);
</script>

    <script src="{{ asset('assets/js/modal-render-chips.js') }}"></script>

</x-app-layout>
