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
            @can('createDiets')
                <div class="overlay" id="modalOverlay">
                    <x-modal-create-diets :specLabels="$specLabels" :consistencyLabels="$consistencyLabels"
                        :foodLabels="$foodLabels"></x-modal-create-diets>
                </div>
            @endcan

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
    .tab-btn.active { background: var(--blue-light); color: var(--blue); border-color: var(--color-borde); font-weight: 600; }
    /* Búsqueda de dietas activas */
    .nd-search { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 12px 14px; border-bottom: 1px solid var(--border); }
    .nd-search-spinner { width: 14px; height: 14px; border: 2px solid var(--border); border-top-color: var(--blue); border-radius: 50%; animation: nd-spin .7s linear infinite; }
    @keyframes nd-spin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { .nd-search-spinner { animation: none; } }
    .nd-search .filter-input { max-width: 280px; }
    .nd-search-info { font-size: 12.5px; color: var(--text-muted); }
    .nd-legend { margin: 0 0 0 auto; }
    @media (max-width: 640px) { .nd-search .filter-input { max-width: none; flex: 1 1 100%; } .nd-legend { margin-left: 0; } }
    /* Estado del día y «Continuar dieta» */
    .nd-day { display:; align-items: center; gap: 4px; flex-wrap: nowrap; white-space: ; }
    .nd-day .tag { margin: 0; }
    .tag-completed { background: var(--color-exito-suave); color: var(--color-exito); font-weight: 600; }
    .tag-in-progress { background: var(--surface2); color: var(--text); }
    .tag-blocked { background: var(--amber-light); color: var(--color-advertencia); font-weight: 600; }
    .tag-continued { background: var(--blue-light); color: var(--blue); }
    .nd-next { display: inline-flex; align-items: center; gap: 3px; height: 22px; padding: 0 8px; border-radius: 999px; font-size: 11.5px; font-weight: 600; line-height: 1; white-space: nowrap; }
    .nd-next svg { width: 12px; height: 12px; fill: none; stroke: currentColor; stroke-width: 2.6; stroke-linecap: round; stroke-linejoin: round; }
    .nd-next-ok { background: var(--blue-light); color: var(--blue); }
    .nd-next-off { color: var(--text-muted); font-weight: 500; }
    .nd-continue { border: 1px solid var(--blue); background: var(--surface); color: var(--blue); cursor: pointer; transition: background .15s, color .15s; }
    .nd-continue:hover { background: var(--blue); color: #fff; }
    .nd-continue:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    .nd-continue[disabled] { opacity: .5; cursor: wait; }
    .nd-tabs { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .nd-tabs .nd-register { margin-left: auto; white-space: nowrap; }
    @media (max-width: 640px) {
        .nd-tabs .tab-btn { flex: 1 1 auto; justify-content: center; }
        .nd-tabs .nd-register { order: -1; flex: 1 1 100%; margin-left: 0; justify-content: center; }
    }
    .nd-notice { margin: 0 0 14px; padding: 10px 14px; border-radius: var(--radius-sm); font-size: 13.5px; background: var(--accent-light); color: var(--accent); }
    .nd-notice.error { background: var(--accent2-light); color: var(--accent2); }
    /* Modal de cancelación / alta */
    .ncm-body p { margin: 0 0 12px; font-size: 13.5px; line-height: 1.55; }
    .ncm-box { margin: 0 0 12px; padding: 10px 14px; border-radius: var(--radius-sm); font-size: 13.5px; line-height: 1.5; }
    .ncm-box ul { margin: 6px 0; padding-left: 18px; list-style: disc; }
    .ncm-box p { margin: 6px 0 0; }
    .ncm-warn { background: var(--amber-light); color: var(--color-advertencia); border-left: 4px solid var(--amber); }
    .ncm-info { background: var(--blue-light); color: var(--blue); }
    .ncm-body .ncm-muted { color: var(--text-muted); font-size: 12.5px; }
    .ncm-transfer { margin-top: 6px; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); }
    .ncm-transfer legend { padding: 0 6px; font-size: 13px; font-weight: 700; }
    .ncm-check { display: flex; align-items: flex-start; gap: 8px; font-weight: 500; cursor: pointer; }
    .ncm-check input { margin-top: 3px; }
    .ncm-label { display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 6px; }
    .ncm-meals { display: flex; flex-wrap: wrap; gap: 8px 16px; }
    .ncm-meal { margin: 0; }
    .ncm-error { margin: 4px 0 0; color: var(--accent2); font-size: 12.5px; }
</style>
<script>
    const SPEC_LABELS = @json($specLabels);
    const TIME_LABELS = @json($foodLabels);
    const CONS_LABELS = @json($consistencyLabels);
</script>

    <script src="{{ asset('assets/js/modal-render-chips.js') }}"></script>

</x-app-layout>
